<?php

namespace Respect\Config;

use ReflectionClass;

class Instantiator
{
    const MODE_DEPENDENCY = false;
    const MODE_FACTORY = 'new';

    protected $instance;
    protected $reflection;
    protected $constructor = array();
    protected $className;
    protected $params = array();
    protected $staticMethodCalls = array();
    protected $methodCalls = array();
    protected $propertySetters = array();
    protected $mode = self::MODE_DEPENDENCY;

    public function __construct($className)
    {
        if (false !== stripos($className, ' ')) {
            list($mode, $className) = explode(' ', $className, 2);
            $this->mode = $mode;
        }
        $this->reflection = new ReflectionClass($className);
        $this->constructor = $this->findConstructorParams($this->reflection);
        $this->className = $className;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function __invoke()
    {
        return call_user_func_array(array($this, 'getInstance'), func_get_args());
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getInstance($forceNew = false)
    {
        if ($this->mode == static::MODE_FACTORY) {
            $this->instance = null;
        }

        if ($this->instance && !$forceNew) {
            return $this->instance;
        }

        $className     = $this->className;
        $staticMethods = count($this->staticMethodCalls);
        foreach ($this->staticMethodCalls as $methodCalls) {
            $this->performMethodCalls(
                $className,
                $methodCalls,
                function ($result) use ($className, &$instance, $staticMethods) {
                    if ($result instanceof $className || ($staticMethods == 1 && is_object($result))) {
                        $instance = $result;
                    }
                }
            );
        }

        $constructor     = $this->reflection->getConstructor();
        $hasConstructor  = ($constructor) ? $constructor->isPublic() : false;
        if (empty($instance)) {
            if (empty($this->constructor) || !$hasConstructor) {
                $instance = new $className();
            } else {
                $instance = $this->reflection->newInstanceArgs(
                    $this->cleanupParams($this->constructor)
                );
            }
        }

        foreach ($this->propertySetters as $property => $value) {
            $instance->{$property} = $this->lazyLoad($value);
        }

        foreach ($this->methodCalls as $methodCalls) {
            $this->performMethodCalls($instance, $methodCalls);
        }

        return $this->instance = $instance;
    }

    public function getParam($name)
    {
        return $this->params[$name];
    }

    public function setInstance($instance)
    {
        $this->instance = $instance;
    }

    public function setParam($name, $value)
    {
        $value = $this->processValue($value);

        if ($this->matchStaticMethod($name)) {
            $this->staticMethodCalls[] = array($name, $value);
        } elseif ($this->matchConstructorParam($name)) {
            $this->constructor[$name] = $value;
        } elseif ($this->matchFullConstructor($name, $value)) {
            $this->constructor = $value;
        } elseif ($this->matchMethod($name)) {
            $this->methodCalls[] = array($name, $value);
        } else {
            $this->propertySetters[$name] = $value;
        }

        $this->params[$name] = $value;
    }

    public function getParams()
    {
        return $this->params;
    }

    protected function cleanupParams(array $params)
    {
        while (null === end($params)) {
            unset($params[key($params)]);
        }

        foreach ($params as &$p) {
            $p = $this->lazyLoad($p);
        }

        return $params;
    }

    protected function lazyLoad($value)
    {
        return $value instanceof self ? $value->getInstance() : $value;
    }

    protected function findConstructorParams(ReflectionClass $class)
    {
        $params = array();
        $constructor = $class->getConstructor();

        if (!$constructor) {
            return array();
        }

        foreach ($constructor->getParameters() as $param) {
            $params[$param->getName()] = $param->isDefaultValueAvailable() ?
                $param->getDefaultValue() : null;
        }

        return $params;
    }

    protected function processValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $valueKey => $subValue) {
                $value[$valueKey] = $this->processValue($subValue);
            }
        }

        return $value;
    }

    protected function matchConstructorParam($name)
    {
        return array_key_exists($name, $this->constructor);
    }

    protected function matchFullConstructor($name, &$value)
    {
        return $name == '__construct'
        || ($name == $this->className && stripos($this->className, '\\'));
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

    protected function performMethodCalls($class, array $methodCalls, $resultCallback = null)
    {
        list($methodName, $calls) = $methodCalls;
        $resultCallback = $resultCallback ?: function () {

        };

        foreach ($calls as $arguments) {
            if (is_array($arguments)) {
                $resultCallback(call_user_func_array(
                    array($class, $methodName),
                    $this->cleanUpParams($arguments)
                ));
            } elseif (!is_null($arguments)) {
                $resultCallback(call_user_func(array($class, $methodName), $this->lazyLoad($arguments)));
            } else {
                $resultCallback(call_user_func(array($class, $methodName)));
            }
        }
    }
}
