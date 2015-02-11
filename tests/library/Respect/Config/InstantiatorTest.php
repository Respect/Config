<?php

namespace Respect\Config;

class InstantiatorTest extends \PHPUnit_Framework_TestCase
{

    public function testStaticMethodConstructor()
    {
        $i = new Instantiator('DateTime');
        $i->setParam('createFromFormat', array(array('Y-m-d', '2005-10-12')));
        $s = $i->getInstance();
        $this->assertEquals($s->format('Y-m-d'), '2005-10-12');
    }

    public function testConstructorParamNames()
    {
        date_default_timezone_set('UTC');
        $i = new Instantiator('DateTime');
        $i->setParam('time', 'now');
        $i->setParam('timezone', $tz = new \DateTimeZone('UTC'));
        $s = $i->getInstance();
        $this->assertEquals('DateTime', get_class($s));
        $this->assertEquals($tz, $s->getTimezone());
    }

    public function testConstructorFull()
    {
        $i = new Instantiator('DateTime');
        $i->setParam('__construct',
            array('now', $tz = new \DateTimeZone('America/Sao_Paulo'))
        );
        $s = $i->getInstance();
        $this->assertEquals('DateTime', get_class($s));
        $this->assertEquals($tz, $s->getTimezone());
    }

    public function testMethodNoParams()
    {
        $i = new Instantiator(__NAMESPACE__ . '\\testClass');
        $i->setParam('noParams', array(
            array()
        ));
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
    }

    public function testMethodWithObjectProperty()
    {
        $i = new Instantiator(__NAMESPACE__ . '\\testClass');
        $i->setParam('myProperty', 'bar');
        $i->setParam('usingProperty', array(
            array()
        ));
        $testObject = $i->getInstance();
        $this->assertTrue($testObject->myPropertyUsed);
    }

    public function testMethodSingleParam()
    {
        $i = new Instantiator(__NAMESPACE__ . '\\testClass');
        $i->setParam('oneParam', array(
            array(true)
        ));
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
    }

    public function testMethodMultiParams()
    {
        $i = new Instantiator(__NAMESPACE__ . '\\testClass');
        $i->setParam('twoParams', array(
            array(true, true)
        ));
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
    }

    public function testConstructorNullParams()
    {
        $i = new Instantiator(__NAMESPACE__ . '\\testClass');
        $i->setParam('__construct', array(true));
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
    }

    public function testConstructorNullParamsFalse()
    {
        $i = new Instantiator(__NAMESPACE__ . '\\testClass');
        $i->setParam('__construct', array(false));
        $s = $i->getInstance();
        $this->assertFalse($s->ok);
    }

    public function testProperties()
    {
        $i = new Instantiator('stdClass');
        $i->setParam('foo', 'bar');
        $i->setParam('baz', 'bat');
        $s = $i->getInstance();
        $this->assertEquals('bar', $s->foo);
        $this->assertEquals('bat', $s->baz);
    }

    public function testNestedInstantiators()
    {
        $i1 = new Instantiator('stdClass');
        $i2 = new Instantiator('stdClass');
        $i1->setParam('foo', $i2);
        $s = $i1->getInstance();
        $this->assertEquals('stdClass', get_class($s->foo));
    }

    public function testMagickInvoke()
    {
        $i1 = new Instantiator('stdClass');
        $i2 = new Instantiator('stdClass');
        $i1->setParam('foo', $i2);
        $s = $i1();
        $this->assertEquals('stdClass', get_class($s->foo));
    }

}

class testClass
{

    public $ok = false;
    public $myPropertyUsed = false;
    public $myProperty = 'foo';

    public function __construct($foo=null, $bar=null, $baz=null)
    {
        if ($foo)
            $this->ok = true;
    }

    public function usingProperty()
    {
        if ($this->myProperty == 'bar')
            $this->myPropertyUsed = true;
    }

    public function noParams()
    {
        if (0 == func_num_args())
            $this->ok = true;
    }

    public function oneParam($ok)
    {
        if ($ok)
            $this->ok = true;
    }

    public function twoParams($ok, $ok2)
    {
        if ($ok && $ok2)
            $this->ok = true;
    }

}

