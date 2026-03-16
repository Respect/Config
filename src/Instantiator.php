<?php

namespace Respect\Config;

use ReflectionClass;

use function array_key_exists;
use function assert;
use function call_user_func;
use function call_user_func_array;
use function count;
use function end;
use function explode;
use function func_get_args;
use function is_array;
use function is_callable;
use function is_object;
use function key;
use function str_contains;
use function stripos;
use function strtolower;

class Instantiator
{
    public const false MODE_DEPENDENCY = false;
    public const string MODE_FACTORY = 'new';

    protected mixed $instance = null;

    /** @var ReflectionClass<object> */
    protected ReflectionClass $reflection;

    /** @var array<string, mixed> */
    protected array $constructor = [];

    /** @var array<string, mixed> */
    protected array $params = [];

    /** @var array<array{string, mixed}> */
    protected array $staticMethodCalls = [];

    /** @var array<array{string, mixed}> */
    protected array $methodCalls = [];

    /** @var array<string, mixed> */
    protected array $propertySetters = [];

    protected string|false $mode = self::MODE_DEPENDENCY;

    /** @param class-string $className */
    public function __construct(protected string $className)
    {
        if (str_contains(strtolower($className), ' ')) {
            [$mode, $className] = explode(' ', $className, 2);
            $this->mode = $mode;
            /** @var class-string $className */
            $this->className = $className;
        }

        $this->reflection = new ReflectionClass($className);
        $this->constructor = $this->findConstructorParams($this->reflection);
    }

    public function getMode(): string|false
    {
        return $this->mode;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getInstance(bool $forceNew = false): mixed
    {
        if ($this->mode === self::MODE_FACTORY) {
            $this->instance = null;
        }

        if ($this->instance && !$forceNew) {
            return $this->instance;
        }

        $className = $this->className;
        $staticMethods = count($this->staticMethodCalls);
        $instance = null;
        foreach ($this->staticMethodCalls as $methodCalls) {
            $this->performMethodCalls(
                $className,
                $methodCalls,
                static function (mixed $result) use ($className, &$instance, $staticMethods): void {
                    if (!($result instanceof $className) && ($staticMethods !== 1 || !is_object($result))) {
                        return;
                    }

                    $instance = $result;
                },
            );
        }

        $constructor = $this->reflection->getConstructor();
        $hasConstructor = $constructor ? $constructor->isPublic() : false;
        if (empty($instance)) {
            if (empty($this->constructor) || !$hasConstructor) {
                $instance = new $className();
            } else {
                $instance = $this->reflection->newInstanceArgs(
                    $this->cleanupParams($this->constructor),
                );
            }
        }

        foreach ($this->propertySetters as $property => $value) {
            $instance->{$property} = $this->lazyLoad($value);
        }

        foreach ($this->methodCalls as $methodCalls) {
            $this->performMethodCalls($instance, $methodCalls);
        }

        return $this->instance = $instance;
    }

    public function getParam(string $name): mixed
    {
        return $this->params[$name];
    }

    public function setInstance(mixed $instance): void
    {
        $this->instance = $instance;
    }

    public function setParam(string $name, mixed $value): void
    {
        $value = $this->processValue($value);

        if ($this->matchStaticMethod($name)) {
            $this->staticMethodCalls[] = [$name, $value];
        } elseif ($this->matchConstructorParam($name)) {
            $this->constructor[$name] = $value;
        } elseif ($this->matchFullConstructor($name)) {
            $this->constructor = is_array($value) ? $value : [];
        } elseif ($this->matchMethod($name)) {
            $this->methodCalls[] = [$name, $value];
        } else {
            $this->propertySetters[$name] = $value;
        }

        $this->params[$name] = $value;
    }

    /** @return array<string, mixed> */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @param array<mixed> $params
     *
     * @return array<mixed>
     */
    protected function cleanupParams(array $params): array
    {
        while (end($params) === null) {
            $key = key($params);
            if ($key === null) {
                break;
            }

            unset($params[$key]);
        }

        foreach ($params as &$p) {
            $p = $this->lazyLoad($p);
        }

        return $params;
    }

    protected function lazyLoad(mixed $value): mixed
    {
        return $value instanceof self ? $value->getInstance() : $value;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return array<string, mixed>
     */
    protected function findConstructorParams(ReflectionClass $class): array
    {
        $params = [];
        $constructor = $class->getConstructor();

        if (!$constructor) {
            return [];
        }

        foreach ($constructor->getParameters() as $param) {
            $params[$param->getName()] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
        }

        return $params;
    }

    protected function processValue(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $valueKey => $subValue) {
                $value[$valueKey] = $this->processValue($subValue);
            }
        }

        return $value;
    }

    protected function matchConstructorParam(string $name): bool
    {
        return array_key_exists($name, $this->constructor);
    }

    protected function matchFullConstructor(string $name): bool
    {
        return $name === '__construct'
            || ($name === $this->className && stripos($this->className, '\\') !== false);
    }

    protected function matchMethod(string $name): bool
    {
        return $this->reflection->hasMethod($name);
    }

    protected function matchStaticMethod(string $name): bool
    {
        return $this->reflection->hasMethod($name)
            && $this->reflection->getMethod($name)->isStatic();
    }

    /** @param array{string, mixed} $methodCalls */
    protected function performMethodCalls(
        object|string $class,
        array $methodCalls,
        callable|null $resultCallback = null,
    ): void {
        [$methodName, $calls] = $methodCalls;
        $resultCallback ??= static function (): void {
        };

        $callable = [$class, $methodName];
        assert(is_callable($callable));

        foreach ($calls as $arguments) {
            if (is_array($arguments)) {
                $resultCallback(call_user_func_array(
                    $callable,
                    $this->cleanupParams($arguments),
                ));
            } elseif ($arguments !== null) {
                $resultCallback(call_user_func($callable, $this->lazyLoad($arguments)));
            } else {
                $resultCallback(call_user_func($callable));
            }
        }
    }

    public function __invoke(): mixed
    {
        return $this->getInstance(...func_get_args());
    }
}
