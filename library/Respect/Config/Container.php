<?php

namespace Respect\Config;

use UnexpectedValueException as Value;
use InvalidArgumentException as Argument;
use ArrayObject;

class Container extends ArrayObject
{

    public function __construct($configurator=null)
    {
        if (is_null($configurator))
            return;
            
        if (is_array($configurator))
            return $this->loadArray($configurator);
            
        if (file_exists($configurator) && is_file($configurator))
            return $this->loadFile($configurator);
            
        if (is_string($configurator))
            return $this->loadString($configurator);
            
        throw new Argument("Invalid input. Must be a valid file or array");
    }

    public function __isset($name)
    {
        return parent::offsetExists($name);
    }
    
    public function getItem($name, $raw=false)
    {
        if (!isset($this[$name]))
            throw new Argument("Item $name not found");
            
        if ($raw || !is_callable($this[$name]))
            return $this[$name];

        return $this->lazyLoad($name);
    }
    
    public function loadString($configurator)
    {
        $iniData = parse_ini_string($configurator);
        if (false === $iniData || count($iniData) == 0)
            throw new Argument("Invalid configuration string");
        
        return $this->loadArray($iniData);
    }

    public function loadFile($configurator)
    {
        $iniData = parse_ini_file($configurator, true);
        if (false === $iniData)
            throw new Argument("Invalid configuration INI file");
        
        return $this->loadArray($iniData);
    }

    public function loadArray(array $configurator)
    {
        foreach ($configurator as $key => $value)
            $this->parseItem($key, $value);
    }

    public function __get($name)
    {
        return $this->getItem($name);
    }
    
    public function __set($name, $value)
    {
        $this[$name] = $value;
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

    protected function parseSubValues(&$value)
    {
        foreach ($value as &$subValue)
            $subValue = $this->parseValue($subValue);
        return $value;
    }

    protected function parseStandardItem($key, &$value)
    {
        if (is_array($value))
            $this->parseSubValues($value);
        else
            $value = $this->parseValue($value);

        $this->offsetSet($key, $value);
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

        $this->offsetSet($keyName, $instantiator);
    }

    protected function parseValue($value)
    {
        if (is_array($value))
            return $this->parseSubValues($value);
        elseif (empty($value))
            return null;
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
        $self = $this;
        return preg_replace_callback(
            '/\[(\w+)\]/',
            function($match) use(&$self) {
                return $self[$match[1]] ? : '';
            }, $value
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
        return $this[$name] = $callback();
    }

}
