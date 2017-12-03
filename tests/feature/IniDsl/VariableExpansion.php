<?php

namespace Test\Feature;

use Respect\Config\Container;

class VariableExpansion extends \PHPUnit_Framework_TestCase
{
    public function testValuesBetweenBracketsAreTreatedAsVariable()
    {
        $c = new Container(<<<INI
            my_name = "John Snow"
            who_knows_nothing = [my_name]
INI
        );

        $this->assertEquals(
            'John Snow',
            $c->getItem('who_knows_nothing'),
            "INI values between brackets should be treated as variables and suffer expansion."
        );
    }

    public function testVariableExpansionUsingStringInterpolation()
    {
        $c = new Container(<<<INI
            db_driver = "mysql"
            db_host   = "localhost"
            db_name   = "my_database"
            db_user   = "root"
            db_pass   = ""
            db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"
INI
        );

        $this->assertEquals(
            'mysql:host=localhost;dbname=my_database',
            $c->getItem('db_dsn'),
            'Variables can be used inside a INI value multiple times.'
        );
    }

    public function testExpansionOfNonExistingVariableDoesNotFailParsing()
    {
        $c = new Container(<<<INI
            my_name =
            who_knows_nothing = [my_name]
INI
        );
    }

    /**
     * @expectedException Respect\Config\NotFoundException
     * @expectedExceptionMessage Item his_name not found
     */
    public function testNonExistingVariableUsageThrowsException()
    {
        $c = new Container(<<<INI
            my_name = "John Snow"
            who_knows_nothing = [his_name]
INI
        );

        $c->who_knows_nothing;
    }

    /**
     * @expectedException Respect\Config\NotFoundException
     * @expectedExceptionMessage Item my_name not found
     */
    public function testExpansionAndUsageOfAnEmptyValueThrowsException()
    {
        $c = new Container(<<<INI
            my_name =
            who_knows_nothing = [my_name]
INI
        );

        $c->who_knows_nothing;
    }

    /**
     * @expectedException Respect\Config\NotFoundException
     * @expectedExceptionMessage Item my_name not found
     */
    public function testExpansionAndUsageOfAnEmptyStringAsValueThrowsException()
    {
        $c = new Container(<<<INI
            my_name = ""
            who_knows_nothing = [my_name]
INI
        );

        $c->who_knows_nothing;
    }

    /**
     * @expectedException Respect\Config\NotFoundException
     * @expectedExceptionMessage Item my_name not found
     */
    public function testExpansionAndUsageOfANullValueThrowsException()
    {
        $c = new Container(<<<INI
            my_name = null
            who_knows_nothing = [my_name]
INI
        );

        $c->who_knows_nothing;
    }

    public function testExpansionInsideASequence()
    {
        $c = new Container(<<<INI
            one = 1
            fibonacci = [[one], [one], 2, 3, 5, 8, 13]
INI
        );

        $this->assertEquals(
            array(1, 1, 2, 3, 5, 8, 13),
            $c->fibonacci,
            'Variable declaration also works inside sequences (or arrays).'
        );
    }
}
