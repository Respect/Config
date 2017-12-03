<?php

namespace Test\Feature;

use Respect\Config\Container;

class ConstantExpansion extends \PHPUnit_Framework_TestCase
{
    public function testExistingConstantExpansionToItsValue()
    {
        $c = new Container(<<<INI
            error_reporting = E_USER_ERROR
INI
        );

        $this->assertEquals(
            E_USER_ERROR,
            $c->error_reporting,
            'Constants are always UPPER CASE, the one being used is already declared by PHP.'
        );
    }

    public function testConstantDeclaredBeforeUsageGetsExpandedCorrectly()
    {
        $c = new Container(<<<INI
            expansion_happens_on_usage = MY_SWEET_CONSTANT_FOR_TESTING
INI
        );

        define('MY_SWEET_CONSTANT_FOR_TESTING', 'foo');

        $this->assertEquals(
            MY_SWEET_CONSTANT_FOR_TESTING,
            $c->expansion_happens_on_usage,
            'Constant expansion happens on first usage, so declaring constants after INI parsing is fine.'
        );
    }

    public function testNonExistingConstantUsageExpandsToItsName()
    {
        $c = new Container(<<<INI
            test_value = NON_EXISTING_CONSTANT
INI
        );

        $this->assertEquals(
            'NON_EXISTING_CONSTANT',
            $c->test_value,
            'Non existing constants are expanded to their name, mimicing PHP behavior.'
        );
    }

    public function testClassConstantExpansionInsideASequence()
    {
        $c = new Container(<<<INI
            pdo_error_mode = [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION]
INI
        );

        $this->assertEquals(
            array(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION),
            $c->pdo_error_mode,
            'Class constants also get expanded! Every feature (e.g: sequences) still works.'
        );
    }

    public function testNonExistingClassConstantExpandsToItsName()
    {
        $c = new Container(<<<INI
            pdo_error_mode = PDO::ERRMODE
INI
        );

        $this->assertEquals(
            'PDO::ERRMODE',
            $c->pdo_error_mode,
            'Non existing class constants get expanded to their full name when used.'
        );
    }

    public function testNonExistingClassConstantInsideASequenceExpandsToItsName()
    {
        $c = new Container(<<<INI
            pdo_error_mode = [PDO::ERRMODE2, PDO::ERRMODE_EXCEPTION]
INI
        );

        $this->assertEquals(
            array('PDO::ERRMODE2', \PDO::ERRMODE_EXCEPTION),
            $c->pdo_error_mode,
            'Class constants also get expanded to their name while inside a sequence.'
        );
    }

    public function testConstantExpansionStringInterpolation()
    {
        $c = new Container(<<<INI
            file_system_path = DIRECTORY_SEPARATOR"var"DIRECTORY_SEPARATOR"log"
INI
        );

        $this->assertEquals(
            "/var/log",
            $c->file_system_path,
            'Constants can be used with string values as well.'
        );
    }
}
