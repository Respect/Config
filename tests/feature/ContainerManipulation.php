<?php

namespace Test\Feature;

use Respect\Config\Container;

class ContainerManipulation extends \PHPUnit_Framework_TestCase
{
    public function testDefiningNewItemOnContainer()
    {
        $c = new Container;

        $c->name = 'John Snow';

        $this->assertEquals(
            'John Snow',
            $c->name,
            'You can define new items just like you would define a property on an instance.'
        );
    }

    public function testDefinitionOfItemWithAnonymousFunctionExecutesItAndReturnsItsValueUponUsage()
    {
        $c = new Container;

        $c->name = function() { return 'John Doe'; };

        $this->assertEquals(
            'John Doe',
            $c->name,
            'The function gets executed and the return value is stored in the container.'
        );
    }

    public function testDefinitionOfItemOnContainerWithItems()
    {
        $c = new Container(<<<INI
respect_blah = ""
INI
        );

        $c->panda = function() { return 'ok'; };

        $this->assertEquals(
            'ok',
            $c->panda,
            'It works if the container has stuff or not.'
        );
    }

    /**
     * @group issue
     * @ticket 14
     */
    public function testItemOverwrrite()
    {
        $c = new Container(<<<INI
[pdo StdClass]

[db Test\Stub\DatabaseWow]
con = [pdo];
INI
        );

        $c->pdo = new \PDO('sqlite::memory:');

        $this->assertNotInstanceOf(
            'StdClass',
            $c->pdo,
            'Although PDO was defined with StdClass we overwritten it to a proper instance of PDO manually.'
        );

        $this->assertSame(
            $c->pdo,
            $c->db->c,
            'This should overwrite every usage of that element inside the container.'
        );
    }
}

namespace Test\Stub;

class DatabaseWow
{
    public $c;

    public function __construct($con)
    {
        $this->c = $con;
    }
}

