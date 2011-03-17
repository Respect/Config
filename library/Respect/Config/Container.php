<?php

namespace Respect\Config;

use UnexpectedValueException;

class Container implements ArrayAccess
{

    protected $items = array();

    public function __construct($configurator=null)
    {
        if (is_null($configurator))
            return;
        elseif (file_exists($configurator))
            $this->loadFromFileSystem($configurator);
        elseif (is_array($configurator))
            $this->loadFromArray($configurator);
    }

    public function loadFromFileSystem($configurator)
    {
        return $this->loadFromArray(parse_ini_file($configurator, true));
    }

    public function loadFromArray($configurator)
    {
        //create new Instantiators from config array
    }

    public function getInstantiator($name, $className)
    {
        
    }

    protected function lazyLoad($name)
    {
        $callback = $this->items[$name];
        return $this->items[$name] = $callback();
    }

    public function getItem($name)
    {
        if (!$this->hasItem($this->items[$name]))
            throw new UnexpectedValueException();
        elseif (is_callable($this->items[$name]))
            return $this->lazyLoad($name);
        else
            return $this->items[$name];
    }

    public function setItem($name, $value)
    {
        $this->items[$name] = $value;
    }

    public function removeItem($name)
    {
        unset($this->items[$name]);
    }

    public function hasItem($name)
    {
        return isset($this->items[$name]);
    }

    public function __set($name, $value)
    {
        return $this->setItem($name, $value);
    }

    public function __get($name)
    {
        return $this->getItem($name);
    }

    public function __isset($name)
    {
        return $this->hasItem($name);
    }

    public function __unset($name)
    {
        return $this->removeItem($name);
    }

    public function offsetExists($name)
    {
        return $this->hasItem($name);
    }

    public function offsetGet($name)
    {
        return $this->getItem($name);
    }

    public function offsetSet($name, $value)
    {
        return $this->setItem($name, $value);
    }

    public function offsetUnset($name)
    {
        return $this->removeItem($name);
    }

}