<?php

namespace Respect\Config;

class Instantiator
{

    protected $instance;
    protected $className;
    protected $params = array();

    public function __construct($className)
    {
        $this->className = $className;
    }

    public function getInstance()
    {
        //From params:
        //1. find constructor params
        //2. instantiate
        //3. call remaining methods and property sets on order
        //4. return instance
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
        $this->params[$name] = $value;
    }

    public function removeItem($name)
    {
        unset($this->params[$name]);
    }

    public function hasItem($name)
    {
        return isset($this->params[$name]);
    }

    public function __set($name, $value)
    {
        return $this->setParam($name, $value);
    }

    public function __get($name)
    {
        return $this->getParam($name);
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
        return $this->getParam($name);
    }

    public function offsetSet($name, $value)
    {
        return $this->setParam($name, $value);
    }

    public function offsetUnset($name)
    {
        return $this->removeItem($name);
    }

}