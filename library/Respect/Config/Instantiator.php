<?php

namespace Respect\Config;

use ReflectionClass;

class Instantiator
{

    protected $instance;
    protected $reflection;
    protected $constructorParams = array();
    protected $className;
    protected $params = array();
    protected $staticMethodCalls = array();
    protected $methodCalls = array();
    protected $propertySetters = array();

    public function __construct($className)
    {
        $this->reflection = new ReflectionClass($className);
        $this->constructorParams = $this->getConstructorParamsNames($this->reflection);
        $this->className = $className;
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getInstance()
    {
        $className = $this->className;
/*
        foreach ($this->staticMethodCalls as $arguments) {
            $methodName = array_shift($arguments);
            $r = call_user_func_array("$className::$methodName", $arguments);
            if ($r instanceof $className)
                $instance = $r;
        }
*/
        if (!isset($instance))
            if (empty($this->constructorParams))
                $instance = new $className;
            else
                $instance = $this->reflection->newInstanceArgs($this->constructorParams);

        foreach ($this->methodCalls as $arguments) {
            $methodName = array_shift($arguments);
            call_user_func_array(array($instance, $methodName), $arguments);
        }

        foreach ($this->propertySetters as $property => $value)
            $instance->{$property} = $value;

        return $instance;
    }

    public function __invoke()
    {
        return call_user_func_array(array($this, 'getInstance'), func_get_args());
    }

    public function getParam($name)
    {
        return $this->params[$name];
    }

    public function setParam($name, $value)
    {
        if ($this->matchStaticMethod($name))
            $this->staticMethodCalls[] = array($name, $value);
        elseif ($this->matchConstructorParam($name))
            $this->constructorParams[$name] = $value;
        elseif ($this->matchFullConstructor($name, $value))
            $this->constructorParams = $value;
        elseif ($this->matchMethod($name))
            $this->methodCalls[] = array($name, $value);
        elseif ($this->matchProperty($name))
            $this->propertySetters[$name] = $value;

        $this->params[$name] = $value;
    }

    protected function getConstructorParamsNames(ReflectionClass $class)
    {
        $params = array();
        $constructor = $class->getConstructor();
        if (!$constructor)
            return array();
        foreach ($constructor->getParameters() as $param) {
            $params[$param->getName()] = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;
        }
        return $params;
    }

    protected function matchStaticMethod($name)
    {
        return $this->reflection->hasMethod($name)
        && $this->reflection->getMethod($name)->isStatic();
    }

    protected function matchConstructorParam($name)
    {
        return array_key_exists($name, $this->constructorParams);
    }

    protected function matchFullConstructor($name, $value)
    {
        return $name == '__construct';
    }

    protected function matchMethod($name)
    {
        return $this->reflection->hasMethod($name);
    }

    protected function matchProperty($name)
    {
        return $this->reflection->hasProperty($name);
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