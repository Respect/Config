<?php

declare(strict_types=1);

namespace Respect\Config;

use ArrayObject;
use Closure;
use Psr\Container\ContainerInterface;
use ReflectionException;

use function call_user_func;
use function class_exists;
use function is_callable;

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

        $entry = parent::offsetGet($id);
        if ($entry instanceof Instantiator) {
            return class_exists($entry->getClassName());
        }

        return true;
    }

    public function getItem(string $name, bool $raw = false): mixed
    {
        if (!parent::offsetExists($name)) {
            throw new NotFoundException('Item ' . $name . ' not found');
        }

        $value = $this[$name];
        if ($raw || !is_callable($value)) {
            return $value;
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
        if ($callback instanceof Instantiator) {
            $result = $callback();
            if ($callback->shouldCache()) {
                $this[$name] = $result;
            }

            return $result;
        }

        if ($callback instanceof Closure) {
            return $this[$name] = $callback($this);
        }

        return $this[$name] = call_user_func($callback);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    public function __get(string $name): mixed
    {
        return $this->getItem($name);
    }

    public function __set(string $name, mixed $value): void
    {
        if (isset($this[$name]) && $this[$name] instanceof Instantiator) {
            // Update the Instantiator's cached instance so other Instantiators
            // holding a reference to it resolve to the new value
            $this[$name]->setInstance($value);
        }

        $this[$name] = $value;
    }
}
