<?php

namespace Test\Feature;

use Respect\Config\Container;

class ItemConsuptionViaCallback extends \PHPUnit_Framework_TestCase
{
    public function testLateDefinitionOfVariableExpansionThroughContainerCallbackReturnsContainer()
    {
        $c = new Container(<<<INI
foo = [undef]
bar = [foo]
INI
        );
        $definition = array('undef' => 'Hello');
        $result = $c($definition);

        $this->assertEquals(
            'Hello',
            $result->bar,
            'Calling the container as a function will append the array passed as content to it.' . PHP_EOL .
            'It will return the itself, as well.'
        );
        $this->assertSame(
            $result->bar,
            $c->bar,
            "But it doesn't matter on which instance of the container you call."
        );
    }

    public function testLateDefinitionOfVariableExpansionThroughItemCallbackReturnsValue()
    {
        $c = new Container(<<<INI
foo = [undef]
bar = [foo]
INI
        );

        $result = $c->bar(array('undef'=>'Hello'));
        $this->assertEquals('Hello', $result);
    }

    public function testRetrievalOfItemThroughInstanceTypeOnContainerCallbackReturnsValue()
    {
        $called = false;
        $c = new Container(<<<INI
[instanceof DateTime]
time = now
INI
        );
        $result = $c(function(\DateTime $date) use (&$called) {
            $called = true;
            return $date;
        });

        $result2 = $c['DateTime'];
        $this->assertInstanceOf('DateTime', $result);
        $this->assertInstanceOf('DateTime', $result2);
        $this->assertTrue($called);
    }

    public function testRetrievalOfInstanceTypeThroughContainerCallbackReturnsValueEvenWithoutDeclaringItsType()
    {
        $c = new Container();
        $c(new \DateTime);
        $called = false;

        $result = $c(function(\DateTime $date) use (&$called) {
            $called = true;
            return $date;
        });

        $result2 = $c['DateTime'];
        $this->assertInstanceOf('DateTime', $result);
        $this->assertInstanceOf('DateTime', $result2);
        $this->assertTrue($called);
    }

    public function testContainerCallbackReceivingACallableCallsItAndReturnsValue()
    {
        $c = new Container();
        $c(new \DateTime);
        $result = $c(array('Test\Stub\TimePrinter', 'returnTimePassedAsArgument'));
        $this->assertInstanceOf('DateTime', $result);
    }
}

namespace Test\Stub;

class TimePrinter
{
    public function returnTimePassedAsArgument(\DateTime $time)
    {
        return $time;
    }
}

