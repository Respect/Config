<?php

declare(strict_types=1);

namespace Respect\Config;

use Closure;
use InvalidArgumentException;

use function constant;
use function count;
use function defined;
use function explode;
use function file_exists;
use function is_array;
use function is_numeric;
use function is_string;
use function parse_ini_file;
use function parse_ini_string;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function str_contains;
use function strstr;
use function trim;

class IniLoader
{
    public function __construct(protected Container $container)
    {
    }

    public static function load(mixed $input, Container|null $container = null): Container
    {
        $container ??= new Container();

        return (new self($container))->interpret($input);
    }

    public function interpret(mixed $input): Container
    {
        if ($input === null) {
            return $this->container;
        }

        if (is_array($input)) {
            return $this->fromArray($input);
        }

        if (is_string($input) && file_exists($input)) {
            return $this->fromFile($input);
        }

        if (is_string($input)) {
            return $this->fromString($input);
        }

        throw new InvalidArgumentException('Invalid input. Must be a valid file or array');
    }

    public function fromString(string $configurator): Container
    {
        $iniData = parse_ini_string($configurator, true);
        if ($iniData === false || count($iniData) === 0) {
            throw new InvalidArgumentException('Invalid configuration string');
        }

        return $this->fromArray($iniData);
    }

    public function fromFile(string $configurator): Container
    {
        $iniData = parse_ini_file($configurator, true);
        if ($iniData === false) {
            throw new InvalidArgumentException('Invalid configuration INI file');
        }

        return $this->fromArray($iniData);
    }

    /** @param array<string, mixed> $configurator */
    public function fromArray(array $configurator): Container
    {
        foreach ($configurator as $key => $value) {
            $stringKey = (string) $key;

            // State takes precedence: use existing non-Instantiator value instead
            if (
                $this->container->offsetExists($stringKey)
                && !$this->container[$stringKey] instanceof Instantiator
            ) {
                $value = $this->container[$stringKey];
            }

            if ($value instanceof Closure) {
                $this->container->offsetSet($stringKey, $value);
                continue;
            }

            if ($value instanceof Instantiator) {
                $this->container->offsetSet($stringKey, $value);
                continue;
            }

            $this->parseItem($key, $value);
        }

        return $this->container;
    }

    protected function existingKeyFrom(string $key): string|false
    {
        $k = strstr($key, ' ', true) ?: $key;

        return $this->container->offsetExists($k) ? $k : false;
    }

    protected function keyHasInstantiator(string $key): bool
    {
        return str_contains($key, ' ');
    }

    protected function parseItem(string|int $key, mixed $value): void
    {
        $key = trim((string) $key);
        if ($this->keyHasInstantiator($key)) {
            $existingKey = $this->existingKeyFrom($key);
            if ($existingKey !== false) {
                $this->container->offsetSet($key, $this->container[$existingKey]);
            } else {
                $this->parseInstantiator($key, $value);
            }
        } else {
            $this->parseStandardItem($key, $value);
        }
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    protected function parseSubValues(array &$value): array
    {
        foreach ($value as &$subValue) {
            $subValue = $this->parseValue($subValue);
        }

        return $value;
    }

    protected function parseStandardItem(string $key, mixed $value): void
    {
        if (is_array($value)) {
            $this->parseSubValues($value);
        } else {
            $value = $this->parseValue($value);
        }

        $this->container->offsetSet($key, $value);
    }

    protected function removeDuplicatedSpaces(string $string): string
    {
        if (!str_contains($string, '  ')) {
            return $string;
        }

        return (string) preg_replace('/\s+/', ' ', $string);
    }

    protected function parseInstantiator(string $key, mixed $value): void
    {
        $key = $this->removeDuplicatedSpaces($key);
        [$keyName, $keyClass] = explode(' ', $key, 2);
        if ($keyName === 'instanceof') {
            $keyName = $keyClass;
        }

        /** @var class-string $keyClass */
        $instantiator = $this->createInstantiator($keyClass);

        if (is_array($value)) {
            foreach ($value as $property => $pValue) {
                $instantiator->setParam($property, $this->parseValue($pValue));
            }
        } else {
            $instantiator->setParam('__construct', $this->parseValue($value));
        }

        $this->container->offsetSet($keyName, $instantiator);
    }

    /** @param class-string $keyClass */
    protected function createInstantiator(string $keyClass): Instantiator
    {
        if (!str_contains($keyClass, ' ')) {
            return new Instantiator($keyClass);
        }

        [$modifier, $className] = explode(' ', $keyClass, 2);

        /** @var class-string $className */
        return match ($modifier) {
            'new' => new Factory($className),
            'autowire' => new Autowire($className),
            default => new Instantiator($keyClass),
        };
    }

    protected function parseValue(mixed $value): mixed
    {
        if ($value instanceof Instantiator) {
            return $value;
        }

        if (is_array($value)) {
            return $this->parseSubValues($value);
        }

        if ($value === '' || $value === null) {
            return null;
        }

        if (!is_string($value)) {
            return $value;
        }

        return $this->parseSingleValue($value);
    }

    protected function hasCompleteBrackets(string $value): bool
    {
        return str_contains($value, '[') && str_contains($value, ']');
    }

    protected function parseSingleValue(string $value): mixed
    {
        $value = trim($value);
        if ($this->hasCompleteBrackets($value)) {
            return $this->parseBrackets($value);
        }

        return $this->parseConstants($value);
    }

    protected function parseConstants(string $value): mixed
    {
        if (preg_match('/^[\\\\a-zA-Z_]+([:]{2}[A-Z_]+)?$/', $value) && defined($value)) {
            return constant($value);
        }

        if (is_numeric($value)) {
            $isFloat = str_contains($value, '.') || str_contains($value, 'e') || str_contains($value, 'E');

            return $isFloat ? (float) $value : (int) $value;
        }

        return $value;
    }

    protected function matchSequence(string &$value): bool
    {
        if (preg_match('/^\[(.*?,.*?)\]$/', $value, $match)) {
            $value = $match[1];

            return true;
        }

        return false;
    }

    protected function matchReference(string &$value): bool
    {
        if (preg_match('/^\[([[:alnum:]_\\\\]+)\]$/', $value, $match)) {
            $value = $match[1];

            return true;
        }

        return false;
    }

    protected function parseBrackets(string $value): mixed
    {
        if ($this->matchSequence($value)) {
            return $this->parseArgumentList($value);
        }

        if ($this->matchReference($value)) {
            return $this->container->getItem($value, true);
        }

        return $this->parseVariables($value);
    }

    protected function parseVariables(string $value): string
    {
        return (string) preg_replace_callback(
            '/\[(\w+)\]/',
            fn(array $match): string => (string) ($this->container[$match[1]] ?? ''),
            $value,
        );
    }

    /** @return array<mixed> */
    protected function parseArgumentList(string $value): array
    {
        $subValues = explode(',', $value);

        return $this->parseSubValues($subValues);
    }
}
