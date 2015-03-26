<?php

namespace Respect\Config;

use InvalidArgumentException as Argument;
use ArrayObject;
use ReflectionClass;
use ReflectionFunction;

class Container extends ArrayObject
{
    protected $configurator;

    public function __construct($configurator = null)
    {
        $this->configurator = $configurator;
    }

    public function __isset($name)
    {
        if ($this->configurator) {
            $this->configure();
        }

        return parent::offsetExists($name);
    }

    public function __invoke($spec)
    {
        if (is_callable($spec)) {
            if (is_array($spec)) {
                list($class, $method) = $spec;
                $class = new ReflectionClass($class);
                $object = $class->newInstance();
                $mirror = $class->getMethod($method);
            } else {
                $object = false;
                $mirror = new ReflectionFunction($spec);
            }
            $container = $this;
            $arguments = array_map(
                function ($param) use ($container) {
                    if ($paramClass = $param->getClass()) {
                        $paramClassName = $paramClass->getName();

                        return $container->getItem($paramClassName);
                    }
                },
                $mirror->getParameters()
            );
            if ($object) {
                return $mirror->invokeArgs($object, $arguments);
            }

            return $mirror->invokeArgs($arguments);
        }
        if ((bool) array_filter(func_get_args(), 'is_object')) {
            foreach (func_get_args() as $dependency) {
                $this[get_class($dependency)] = $dependency;
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

    public function __call($name, $dict)
    {
        $this->__invoke($dict[0]);

        return $this->getItem($name);
    }

    protected function configure()
    {
        $configurator = $this->configurator;
        $this->configurator = null;

        if (is_null($configurator)) {
            return;
        }

        if (is_array($configurator)) {
            return $this->loadArray($configurator);
        }

        if (file_exists($configurator)) {
            return $this->loadFile($configurator);
        }

        if (is_string($configurator)) {
            return $this->loadString($configurator);
        }

        throw new Argument("Invalid input. Must be a valid file or array");
    }

    public function getItem($name, $raw = false)
    {
        if ($this->configurator) {
            $this->configure();
        }

        if (!isset($this[$name])) {
            throw new Argument("Item $name not found");
        }

        if ($raw || !is_callable($this[$name])) {
            return $this[$name];
        }

        return $this->lazyLoad($name);
    }

    public function loadString($configurator)
    {
        $iniData = parse_ini_string($configurator, true);
        if (false === $iniData || count($iniData) == 0) {
            throw new Argument("Invalid configuration string");
        }

        return $this->loadArray($iniData);
    }

    public function loadFile($configurator)
    {
        $iniData = parse_ini_file($configurator, true);
        if (false === $iniData) {
            throw new Argument("Invalid configuration INI file");
        }

        return $this->loadArray($iniData);
    }

    protected function state()
    {
        return array_filter($this->getArrayCopy(), function ($v) {
            return !is_object($v) || !$v instanceof Instantiator;
        });
    }

    public function loadArray(array $configurator)
    {
        foreach ($this->state() + $configurator as $key => $value) {
            if ($value instanceof \Closure) {
                continue;
            }
            $this->parseItem($key, $value);
        }
    }

    public function __get($name)
    {
        return $this->getItem($name);
    }

    public function __set($name, $value)
    {
        if (isset($this[$name]) && $this[$name] instanceof Instantiator) {
            $this[$name]->setInstance($value);
        }
        $this[$name] = $value;
    }

    protected function keyHasStateInstance($key, &$k)
    {
        return $this->offsetExists($k = current((explode(' ', $key))));
    }

    protected function keyHasInstantiator($key)
    {
        return false !== stripos($key, ' ');
    }

    protected function parseItem($key, $value)
    {
        $key = trim($key);
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

    protected function parseSubValues(&$value)
    {
        foreach ($value as &$subValue) {
            $subValue = $this->parseValue($subValue);
        }

        return $value;
    }

    protected function parseStandardItem($key, &$value)
    {
        if (is_array($value)) {
            $this->parseSubValues($value);
        } else {
            $value = $this->parseValue($value);
        }

        $this->offsetSet($key, $value);
    }

    protected function removeDuplicatedSpaces($string)
    {
        return preg_replace('/\s+/', ' ', $string);
    }

    protected function parseInstantiator($key, $value)
    {
        $key = $this->removeDuplicatedSpaces($key);
        list($keyName, $keyClass) = explode(' ', $key, 2);
        if ('instanceof' === $keyName) {
            $keyName = $keyClass;
        }
        $instantiator = new Instantiator($keyClass);

        if (is_array($value)) {
            foreach ($value as $property => $pValue) {
                $instantiator->setParam($property, $this->parseValue($pValue));
            }
        } else {
            $instantiator->setParam('__construct', $this->parseValue($value));
        }

        $this->offsetSet($keyName, $instantiator);
    }

    protected function parseValue($value)
    {
        if ($value instanceof Instantiator) {
            return $value;
        } elseif (is_array($value)) {
            return $this->parseSubValues($value);
        } elseif (empty($value)) {
            return null;
        } else {
            return $this->parseSingleValue($value);
        }
    }
    protected function hasCompleteBrackets($value)
    {
        return false !== strpos($value, '[') && false !== strpos($value, ']');
    }

    protected function parseSingleValue($value)
    {
        $value = trim($value);
        if ($this->hasCompleteBrackets($value)) {
            return $this->parseBrackets($value);
        } else {
            return $this->parseConstants($value);
        }
    }

    protected function parseConstants($value)
    {
        if (preg_match('/^[\\a-zA-Z_]+([:]{2}[A-Z_]+)?$/', $value) && defined($value)) {
            return constant($value);
        } else {
            return $value;
        }
    }

    protected function matchSequence(&$value)
    {
        if (preg_match('/^\[(.*?,.*?)\]$/', $value, $match)) {
            return (boolean) ($value = $match[1]);
        }
    }

    protected function matchReference(&$value)
    {
        if (preg_match('/^\[([[:alnum:]_\\\\]+)\]$/', $value, $match)) {
            return (boolean) ($value = $match[1]);
        }
    }

    protected function parseBrackets($value)
    {
        if ($this->matchSequence($value)) {
            return $this->parseArgumentList($value);
        } elseif ($this->matchReference($value)) {
            return $this->getItem($value, true);
        } else {
            return $this->parseVariables($value);
        }
    }

    protected function parseVariables($value)
    {
        $self = $this;

        return preg_replace_callback(
            '/\[(\w+)\]/',
            function ($match) use (&$self) {
                return $self[$match[1]] ?: '';
            },
            $value
        );
    }

    protected function parseArgumentList($value)
    {
        $subValues = explode(',', $value);

        return $this->parseSubValues($subValues);
    }

    protected function lazyLoad($name)
    {
        $callback = $this[$name];
        if ($callback instanceof Instantiator && $callback->getMode() != Instantiator::MODE_FACTORY) {
            return $this[$name] = $callback();
        }

        return $callback();
    }
}
