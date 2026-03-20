<?php

declare(strict_types=1);

namespace Respect\Config;

use ArrayObject;
use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;

use function array_filter;
use function array_map;
use function assert;
use function call_user_func;
use function class_exists;
use function constant;
use function count;
use function current;
use function defined;
use function explode;
use function file_exists;
use function func_get_args;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function parse_ini_file;
use function parse_ini_string;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function str_contains;
use function trim;

/** @extends ArrayObject<string, mixed> */
class Container extends ArrayObject implements ContainerInterface
{
    public function __construct(protected mixed $configurator = null)
    {
    }

    public function has(string $id): bool
    {
        if ($this->configurator) {
            $this->configure();
        }

        if (!parent::offsetExists($id)) {
            return false;
        }

        $entry = $this[$id];
        if ($entry instanceof Instantiator) {
            return class_exists($entry->getClassName());
        }

        return true;
    }

    public function getItem(string $name, bool $raw = false): mixed
    {
        if ($this->configurator) {
            $this->configure();
        }

        if (!isset($this[$name])) {
            throw new NotFoundException('Item ' . $name . ' not found');
        }

        if ($raw || !is_callable($this[$name])) {
            return $this[$name];
        }

        try {
            return $this->lazyLoad($name);
        } catch (ReflectionException $e) {
            throw new NotFoundException('Item ' . $name . ' not found: ' . $e->getMessage(), 0, $e);
        }
    }

    public function get(string $id): mixed
    {
        return $this->getItem($id);
    }

    public function loadString(string $configurator): void
    {
        $iniData = parse_ini_string($configurator, true);
        if ($iniData === false || count($iniData) === 0) {
            throw new InvalidArgumentException('Invalid configuration string');
        }

        $this->loadArray($iniData);
    }

    public function loadFile(string $configurator): void
    {
        $iniData = parse_ini_file($configurator, true);
        if ($iniData === false) {
            throw new InvalidArgumentException('Invalid configuration INI file');
        }

        $this->loadArray($iniData);
    }

    /** @param array<string, mixed> $configurator */
    public function loadArray(array $configurator): void
    {
        foreach ($this->state() + $configurator as $key => $value) {
            if ($value instanceof Closure) {
                $this->offsetSet((string) $key, $value);
                continue;
            }

            if ($value instanceof Instantiator) {
                $this->offsetSet((string) $key, $value);
                continue;
            }

            $this->parseItem($key, $value);
        }
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        if ($value instanceof Autowire) {
            $value->setContainer($this);
        }

        parent::offsetSet($key, $value);
    }

    public function set(string $name, mixed $value): void
    {
        $this[$name] = $value;
    }

    protected function configure(): void
    {
        $configurator = $this->configurator;
        $this->configurator = null;

        if ($configurator === null) {
            return;
        }

        if (is_array($configurator)) {
            $this->loadArray($configurator);

            return;
        }

        if (is_string($configurator) && file_exists($configurator)) {
            $this->loadFile($configurator);

            return;
        }

        if (is_string($configurator)) {
            $this->loadString($configurator);

            return;
        }

        throw new InvalidArgumentException('Invalid input. Must be a valid file or array');
    }

    /** @return array<string, mixed> */
    protected function state(): array
    {
        return array_filter(
            $this->getArrayCopy(),
            static fn($v): bool => !is_object($v) || !$v instanceof Instantiator,
        );
    }

    protected function keyHasStateInstance(string $key, mixed &$k): bool
    {
        return $this->offsetExists($k = current(explode(' ', $key)));
    }

    protected function keyHasInstantiator(string $key): bool
    {
        return str_contains($key, ' ');
    }

    protected function parseItem(string|int $key, mixed $value): void
    {
        $key = trim((string) $key);
        if ($this->keyHasInstantiator($key)) {
            if ($this->keyHasStateInstance($key, $k)) {
                $this->offsetSet($key, $this[$k]);
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

    protected function parseStandardItem(string $key, mixed &$value): void
    {
        if (is_array($value)) {
            $this->parseSubValues($value);
        } else {
            $value = $this->parseValue($value);
        }

        $this->offsetSet($key, $value);
    }

    protected function removeDuplicatedSpaces(string $string): string
    {
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

        $this->offsetSet($keyName, $instantiator);
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

        if (empty($value)) {
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
            return $this->getItem($value, true);
        }

        return $this->parseVariables($value);
    }

    protected function parseVariables(string $value): string
    {
        return (string) preg_replace_callback(
            '/\[(\w+)\]/',
            fn(array $match): string => $this[$match[1]] ?: '',
            $value,
        );
    }

    /** @return array<mixed> */
    protected function parseArgumentList(string $value): array
    {
        $subValues = explode(',', $value);

        return $this->parseSubValues($subValues);
    }

    protected function lazyLoad(string $name): mixed
    {
        $callback = $this[$name];
        if ($callback instanceof Instantiator && !$callback instanceof Factory) {
            return $this[$name] = $callback();
        }

        if ($callback instanceof Closure) {
            return $this[$name] = $callback($this);
        }

        return call_user_func($callback);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    public function __invoke(mixed $spec): mixed
    {
        if (is_callable($spec)) {
            if (is_array($spec)) {
                [$class, $method] = $spec;
                $class = new ReflectionClass($class);
                $object = $class->newInstance();
                $mirror = $class->getMethod($method);
            } else {
                $object = false;
                assert($spec instanceof Closure || is_string($spec));
                $mirror = new ReflectionFunction($spec);
            }

            $container = $this;
            $arguments = array_map(
                static function ($param) use ($container) {
                    $paramClass = $param->getType();
                    if ($paramClass instanceof ReflectionNamedType) {
                        return $container->getItem($paramClass->getName());
                    }

                    return null;
                },
                $mirror->getParameters(),
            );
            if ($object) {
                return $mirror->invokeArgs($object, $arguments);
            }

            return $mirror->invokeArgs($arguments);
        }

        if ((bool) array_filter(func_get_args(), 'is_object')) {
            foreach (func_get_args() as $dependency) {
                $this[$dependency::class] = $dependency;
            }
        }

        foreach ($spec as $name => $item) {
            parent::offsetSet($name, $item);
        }

        if ($this->configurator) {
            $this->configure();
        }

        return $this;
    }

    /** @param array<mixed> $dict */
    public function __call(string $name, array $dict): mixed
    {
        $this->__invoke($dict[0]);

        return $this->getItem($name);
    }

    public function __get(string $name): mixed
    {
        return $this->getItem($name);
    }

    public function __set(string $name, mixed $value): void
    {
        if (isset($this[$name]) && $this[$name] instanceof Instantiator) {
            $this[$name]->setInstance($value);
        }

        $this[$name] = $value;
    }
}
