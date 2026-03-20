<?php

namespace Respect\Config;

use ReflectionClass;

use function array_key_exists;
use function assert;
use function call_user_func;
use function call_user_func_array;
use function count;
use function end;
use function func_get_args;
use function is_array;
use function is_callable;
use function is_object;
use function key;
use function stripos;

class Instantiator
{
    protected mixed $instance = null;

    /** @var ReflectionClass<object>|null */
    protected ReflectionClass|null $reflection = null;

    /** @var array<string, mixed>|null */
    protected array|null $constructor = null;

    /** @var array<string, mixed> */
    protected array $params = [];

    /** @var array<array{string, mixed}> */
    protected array $staticMethodCalls = [];

    /** @var array<array{string, mixed}> */
    protected array $methodCalls = [];

    /** @var array<string, mixed> */
    protected array $propertySetters = [];

    /**
     * @param class-string $className
     * @param array<string, mixed> $params    Initial parameters (constructor, method, or property)
     */
    public function __construct(protected string $className, array $params = [])
    {
        foreach ($params as $name => $value) {
            $this->setParam($name, $value);
        }
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getInstance(bool $forceNew = false): mixed
    {
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

        if (empty($instance)) {
            $constructorParams = $this->cleanupParams($this->constructor ?? []);
            if (empty($constructorParams)) {
                $instance = $this->reflection()->newInstance();
            } else {
                $instance = $this->reflection()->newInstanceArgs($constructorParams);
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

    /** @return ReflectionClass<object> */
    protected function reflection(): ReflectionClass
    {
        return $this->reflection ??= new ReflectionClass($this->className);
    }

    /**
     * @param array<mixed> $params
     *
     * @return array<mixed>
     */
    protected function cleanupParams(array $params): array
    {
        while (end($params) === null && ($key = key($params)) !== null) {
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
        if ($this->constructor === null) {
            $this->constructor = [];
            $ctor = $this->reflection()->getConstructor();
            if ($ctor) {
                foreach ($ctor->getParameters() as $param) {
                    $this->constructor[$param->getName()] = null;
                }
            }
        }

        return array_key_exists($name, $this->constructor);
    }

    protected function matchFullConstructor(string $name): bool
    {
        return $name === '__construct'
            || ($name === $this->className && stripos($this->className, '\\') !== false);
    }

    protected function matchMethod(string $name): bool
    {
        return $this->reflection()->hasMethod($name);
    }

    protected function matchStaticMethod(string $name): bool
    {
        return $this->reflection()->hasMethod($name)
            && $this->reflection()->getMethod($name)->isStatic();
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
