<?php

namespace Respect\Config;

use UnexpectedValueException;
use InvalidArgumentException;
use ArrayAccess;

class Container implements ArrayAccess
{

    protected $items = array();

    public function __construct($configurator=null)
    {
        if (is_null($configurator))
            return;
        elseif (is_array($configurator))
            $this->loadArray($configurator);
        elseif (file_exists($configurator) && is_file($configurator))
            $this->loadFile($configurator);
        else //FIXME
            throw new InvalidArgumentException();
    }

    public function loadFile($configurator)
    {
        return $this->loadArray(parse_ini_file($configurator, true));
    }

    public function loadArray(array $configurator)
    {
        foreach ($configurator as $key => $value)
            $this->parseItem($key, $value);
    }

    protected function keyHasInstantiator($key)
    {
        return false !== stripos($key, ' ');
    }

    protected function parseItem($key, $value)
    {
        $key = trim($key);
        if ($this->keyHasInstantiator($key))
            $this->parseInstantiator($key, $value);
        else
            $this->parseStandardItem($key, $value);
    }

    protected function parseSubValues($value)
    {
        foreach ($value as &$subValue)
            $subValue = $this->parseValue($subValue);
        return $value;
    }

    protected function parseStandardItem($key, $value)
    {
        if (is_array($value))
            $this->parseSubValues(&$value);
        else
            $value = $this->parseValue($value);

        $this->setItem($key, $value);
    }

    protected function removeDuplicatedSpaces($string)
    {
        return preg_replace('/\s+/', ' ', $string);
    }

    protected function parseInstantiator($key, $value)
    {
        $key = $this->removeDuplicatedSpaces($key);
        list($keyName, $keyClass) = explode(' ', $key);
        $instantiator = new Instantiator($keyClass);

        if (is_array($value))
            foreach ($value as $property => $pValue)
                $instantiator->setParam($property, $this->parseValue($pValue));
        else
            $instantiator->setParam('__construct', $this->parseValue($value));

        $this->setItem($keyName, $instantiator);
    }

    protected function parseValue($value)
    {
        if (is_array($value))
            return $this->parseSubValues($value);
        else
            return $this->parseSingleValue($value);
    }

    protected function hasCompleteBrackets($value)
    {
        return false !== strpos($value, '[') && false !== strpos($value, ']');
    }

    protected function parseSingleValue($value)
    {
        $value = trim($value);
        if ($this->hasCompleteBrackets($value))
            return $this->parseBrackets($value);
        else
            return $this->parseConstants($value);
    }

    protected function parseConstants($value)
    {
        if (preg_match('/^[A-Z_]+([:]{2}[A-Z_]+)?$/', $value) && defined($value))
            return constant($value);
        else
            return $value;
    }

    protected function matchSequence(&$value)
    {
        if (preg_match('/^\[(.*?,.*?)\]$/', $value, $match))
            return (boolean) ($value = $match[1]);
    }

    protected function matchReference(&$value)
    {
        if (preg_match('/^\[(\w+)+\]$/', $value, $match))
            return (boolean) ($value = $match[1]);
    }

    protected function parseBrackets($value)
    {
        if ($this->matchSequence($value))
            return $this->parseArgumentList($value);
        elseif ($this->matchReference($value))
            return $this->getItem($value, true);
        else
            return $this->parseVariables($value);
    }

    protected function parseVariables($value)
    {
        $vars = $this->items;
        return preg_replace_callback(
            '/\[(\w+)\]/',
            function($match) use($vars) {
                return $vars[$match[1]] ? : '';
            }, $value
        );
    }

    protected function parseArgumentList($value)
    {
        return $this->parseSubValues(explode(',', $value));
    }

    protected function lazyLoad($name)
    {
        $callback = $this->items[$name];
        return $this->items[$name] = $callback();
    }

    public function getItem($name, $raw=false)
    {
        if (!$this->hasItem($name))
            throw new UnexpectedValueException();
        elseif ($raw || !is_callable($this->items[$name]))
            return $this->items[$name];
        else
            return $this->lazyLoad($name);
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