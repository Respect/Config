<?php

namespace Test\Feature;

use Respect\Config\Container;

class ContainerAsArray extends \PHPUnit_Framework_TestCase
{
    /**
     * @TODO Fix issue where passing INI to container construction does not
     *       allow ArrayAccess usage. T.T
     */
    public function testUsingItemsAsContainerWasAnArray()
    {
        $c = new Container();
        $c->loadString(<<<INI
fibonacci = [1, 1, 2, 3, 5]
INI
        );

        $this->assertInstanceOf(
            'ArrayAccess',
            $c,
            'The container implements the \ArrayAccess interface, so it behaves like an array.'
        );
        $this->assertTrue(
            isset($c['fibonacci'])
        );
        $this->assertEquals(
            array(1, 1, 2, 3, 5),
            $c['fibonacci'],
            'The container implements the \ArrayAccess interface, so it behaves like an array.'
        );
    }

    public function testDefiningItemWithArrayLikeNotation()
    {
        $c = new Container;

        $c['not'] = false;
        $this->assertTrue(isset($c['not']));
        $this->assertFalse($c['not']);
    }
}

