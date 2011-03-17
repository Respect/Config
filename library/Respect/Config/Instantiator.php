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

    public function getClassName()
    {
        return $this->className;
    }

    public function getInstance()
    {
        $className = new $this->className;
        $instance = new $className;
        return $instance;
        //From params:
        //0. find static factory methods
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

    public function removeParam($name)
    {
        unset($this->params[$name]);
    }

    public function hasParam($name)
    {
        return isset($this->params[$name]);
    }

}