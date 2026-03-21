<?php

declare(strict_types=1);

namespace Respect\Config;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

use function array_slice;
use function assert;
use function call_user_func;
use function call_user_func_array;
use function count;
use function in_array;
use function is_array;
use function is_callable;
use function is_object;
use function str_contains;

class Instantiator
{
    protected mixed $instance = null;

    /** @var ReflectionClass<object>|null */
    protected ReflectionClass|null $reflection = null;

    /** @var array<string, mixed>|null */
    protected array|null $constructor = null;

    /** @var list<string>|null */
    private array|null $constructorParamNames = null;

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

    public function shouldCache(): bool
    {
        return true;
    }

    public function getInstance(): mixed
    {
        if ($this->shouldCache() && $this->instance) {
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

        if ($instance === null) {
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

        if ($this->shouldCache()) {
            $this->instance = $instance;
        }

        return $instance;
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
        if ($this->matchFullConstructor($name)) {
            $this->constructor = is_array($value) ? $value : [];
        } elseif ($this->matchConstructorParam($name)) {
            $this->constructor[$name] = $value;
        } elseif ($this->matchStaticMethod($name)) {
            $this->staticMethodCalls[] = [$name, $value];
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
    protected function cleanupParams(array $params, bool $forConstructor = true): array
    {
        $result = [];
        foreach ($params as $key => $value) {
            $result[$key] = $this->lazyLoad($value);
        }

        return $this->stripTrailingNulls($result);
    }

    /**
     * @param array<mixed> $params
     *
     * @return array<mixed>
     */
    protected function stripTrailingNulls(array $params): array
    {
        $lastNonNull = -1;
        $i = 0;

        foreach ($params as $value) {
            if ($value !== null) {
                $lastNonNull = $i;
            }

            ++$i;
        }

        return array_slice($params, 0, $lastNonNull + 1, true);
    }

    protected function lazyLoad(mixed $value): mixed
    {
        if ($value instanceof Ref) {
            throw new InvalidArgumentException('Ref can only be used with Autowire, not ' . static::class);
        }

        return $value instanceof self ? $value->getInstance() : $value;
    }

    protected function matchConstructorParam(string $name): bool
    {
        if ($this->constructorParamNames === null) {
            $this->constructorParamNames = [];
            $this->constructor ??= [];
            $ctor = $this->reflection()->getConstructor();
            if ($ctor) {
                foreach ($ctor->getParameters() as $param) {
                    $this->constructorParamNames[] = $param->getName();
                }
            }
        }

        return in_array($name, $this->constructorParamNames, true);
    }

    protected function matchFullConstructor(string $name): bool
    {
        return $name === '__construct'
            || ($name === $this->className && str_contains($this->className, '\\'));
    }

    protected function matchMethod(string $name): bool
    {
        return $this->reflection()->hasMethod($name);
    }

    protected function matchStaticMethod(string $name): bool
    {
        try {
            return $this->reflection()->getMethod($name)->isStatic();
        } catch (ReflectionException) {
            return false;
        }
    }

    /** @param array{string, mixed} $methodCalls */
    protected function performMethodCalls(
        object|string $class,
        array $methodCalls,
        callable|null $resultCallback = null,
    ): void {
        [$methodName, $calls] = $methodCalls;

        $callable = [$class, $methodName];
        assert(is_callable($callable));

        foreach ($calls as $arguments) {
            if (is_array($arguments)) {
                $result = call_user_func_array($callable, $this->cleanupParams($arguments, false));
            } elseif ($arguments !== null) {
                $result = call_user_func($callable, $this->lazyLoad($arguments));
            } else {
                $result = call_user_func($callable);
            }

            if ($resultCallback === null) {
                continue;
            }

            $resultCallback($result);
        }
    }

    public function __invoke(): mixed
    {
        return $this->getInstance();
    }
}
