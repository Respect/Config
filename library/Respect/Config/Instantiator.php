<?php

namespace Respect\Config;

use ReflectionClass;

class Instantiator
{

    protected $instance;
    protected $reflection;
    protected $constructor = array();
    protected $className;
    protected $params = array();
    protected $staticMethodCalls = array();
    protected $methodCalls = array();
    protected $propertySetters = array();

    public function __construct($className)
    {
        $this->reflection = new ReflectionClass($className);
        $this->constructor = $this->findConstructorParams($this->reflection);
        $this->className = $className;
    }

    public function __invoke()
    {
        return call_user_func_array(array($this, 'getInstance'), func_get_args());
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getInstance($forceNew=false)
    {
        if ($this->instance && !$forceNew)
            return $this->instance;

        $className = $this->className;
        $instance = $this->instance;

        foreach ($this->staticMethodCalls as $methodCalls) {
            $this->performMethodCalls($className, $methodCalls,
                function($result) use ($className, &$instance) {
                    if ($result instanceof $className)
                        $instance = $result;
                }
            );
        }

        if (empty($instance))
            if (empty($this->constructor))
                $instance = new $className;
            else
                $instance = $this->reflection->newInstanceArgs(
                        $this->cleanupParams($this->constructor)
                );

        foreach ($this->propertySetters as $property => $value)
            $instance->{$property} = $value;
            
        foreach ($this->methodCalls as $methodCalls)
            $this->performMethodCalls($instance, $methodCalls);
            

        return $instance;
    }

    public function getParam($name)
    {
        return $this->params[$name];
    }

    public function setParam($name, $value)
    {
        $value = $this->processValue($value);

        if ($this->matchStaticMethod($name))
            $this->staticMethodCalls[] = array($name, $value);
        elseif ($this->matchConstructorParam($name))
            $this->constructor[$name] = $value;
        elseif ($this->matchFullConstructor($name, $value))
            $this->constructor = $value;
        elseif ($this->matchMethod($name))
            $this->methodCalls[] = array($name, $value);
        else
            $this->propertySetters[$name] = $value;

        $this->params[$name] = $value;
    }

    protected function cleanupParams(array $params)
    {
        while (null === end($params))
            unset($params[key($params)]);
        return $params;
    }

    protected function findConstructorParams(ReflectionClass $class)
    {
        $params = array();
        $constructor = $class->getConstructor();

        if (!$constructor)
            return array();

        foreach ($constructor->getParameters() as $param)
            $params[$param->getName()] = $param->isDefaultValueAvailable() ?
                $param->getDefaultValue() : null;

        return $params;
    }

    protected function processValue($value)
    {
        if ($value instanceof self)
            $value = $value->getInstance();
        elseif (is_array($value))
            foreach ($value as $valueKey => $subValue) 
                $value[$valueKey] = $this->processValue($subValue);

        return $value;
    }

    protected function matchConstructorParam($name)
    {
        return array_key_exists($name, $this->constructor);
    }

    protected function matchFullConstructor($name, &$value)
    {
        return $name == '__construct'
        || ( $name == $this->className && stripos($this->className, '\\'));
    }

    protected function matchMethod($name)
    {
        return $this->reflection->hasMethod($name);
    }

    protected function matchStaticMethod($name)
    {
        return $this->reflection->hasMethod($name)
        && $this->reflection->getMethod($name)->isStatic();
    }

    protected function performMethodCalls($class, array $methodCalls, $resultCallback=null)
    {
        list($methodName, $calls) = $methodCalls;
        foreach ($calls as $arguments) {
            if (is_array($arguments))
                $result = call_user_func_array(array($class, $methodName),
                        $this->cleanUpParams($arguments));
            elseif (!is_null($arguments))
                $result = call_user_func(array($class, $methodName), $arguments);
            else
                $result = call_user_func(array($class, $methodName));
            if ($resultCallback)
                $resultCallback($result);
        }
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