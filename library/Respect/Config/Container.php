<?php

namespace Respect\Config;

use UnexpectedValueException;
use InvalidArgumentException;
use ArrayObject;

class Container extends ArrayObject
{

    public function __construct($configurator=null)
    {
        parent::__construct(array(), static::STD_PROP_LIST | static::ARRAY_AS_PROPS);
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
        return $this->parseSubValues(explode(',', $value));
    }

    protected function lazyLoad($name)
    {
        $callback = $this[$name];
        return $this[$name] = $callback();
    }

    public function getItem($name, $raw=false)
    {
        if (!$this->hasItem($name))
            throw new UnexpectedValueException();
        elseif ($raw || !is_callable($this[$name]))
            return $this[$name];
        else
            return $this->lazyLoad($name);
    }

    public function setItem($name, $value)
    {
        return $this->offsetSet($name, $value);
    }

    public function removeItem($name)
    {
        return $this->offsetunset($name);
    }

    public function hasItem($name)
    {
        return $this->offsetExists($name);
    }

}

/**
 * LICENSE
 *
 * Copyright (c) 2009-2011, Alexandre Gomes Gaigalas.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *     * Neither the name of Alexandre Gomes Gaigalas nor the names of its
 *       contributors may be used to endorse or promote products derived from this
 *       software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */