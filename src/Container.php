<?php

declare(strict_types=1);

namespace Respect\Config;

use ArrayObject;
use Closure;
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
use function func_get_args;
use function is_array;
use function is_callable;
use function is_string;

/** @extends ArrayObject<string, mixed> */
class Container extends ArrayObject implements ContainerInterface
{
    /** @param array<string, mixed> $definitions */
    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $key => $value) {
            $this->offsetSet((string) $key, $value);
        }
    }

    public function has(string $id): bool
    {
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
