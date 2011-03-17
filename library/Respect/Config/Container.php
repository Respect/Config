<?php

namespace Respect\Config;

use UnexpectedValueException;
use InvalidArgumentException;

class Container
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

    protected function parseItem($key, $value)
    {
        $key = trim($key);
        if (false !== stripos($key, ' '))
            $this->parseInstantiator($key, $value);
        else
            $this->parseStandardItem($key, $value);
    }

    protected function parseStandardItem($key, $value)
    {
        if (is_array($value))
            foreach ($value as &$subValue)
                $subValue = $this->parseValue($subValue);
        else
            $value = $this->parseValue($value);

        $this->setItem($key, $value);
    }

    protected function parseInstantiator($key, $value)
    {
        $key = preg_replace('/\s+/', ' ', $key);
        list($keyName, $keyClass) = explode(' ', $key);
        $instantiator = new Instantiator($keyClass);

        if (is_array($value))
            foreach ($value as $property => $pValue)
                $this->setInstantiatorParams($instantiator, $property, $pValue);
        else
            $this->setInstantiatorParams($instantiator, '__construct', $value);

        $this->setItem($keyName, $instantiator);
    }

    protected function setInstantiatorParams($instantiator, $key, $value)
    {
        if (is_array($value))
            foreach ($value as $subValue)
                $this->setInstantiatorParams($instantiator, $key, $subValue);
        else
            $instantiator->setParam($key, $this->parseValue($value));
    }

    protected function parseValue($value)
    {
        $value = trim($value);
        if (false === strpos($value, '['))
            return $value;
        elseif (false === strpos($value, ']'))
            return $value;
        else
            return $this->parseBrackets($value);
    }

    protected function parseBrackets($value)
    {
        if (preg_match('/^\[(.*?,.*?)+\]$/', $value, $matches))
            return $this->parseArgumentList($value, $matches[1]);
        else
            return $this->parseVariables($value);
    }

    protected function parseVariables($value)
    {
        $vars = $this->items;
        return preg_replace_callback(
            '/\[(\w+)\]/',
            function($match) use($vars) {
                return isset($vars[$match[1]]) ? (string) $vars[$match[1]] : '';
            }, $value
        );
    }

    protected function parseArgumentList($value)
    {
        $values = explode(',', $value);
        foreach ($values as &$v)
            $v = $this->parseValue($v);
        return $values;
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

}