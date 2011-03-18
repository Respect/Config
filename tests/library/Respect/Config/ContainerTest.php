<?php

namespace Respect\Config;

class ContainerTest extends \PHPUnit_Framework_TestCase
{

    public function testLoadArray()
    {
        $ini = <<<INI
foo = bar
baz = bat
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testLoadArraySections()
    {
        $ini = <<<INI
[sec]
foo = bar
baz = bat
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $d = $c->getItem('sec');
        $this->assertEquals('bar', $d['foo']);
        $this->assertEquals('bat', $d['baz']);
    }

    public function testExpandVars()
    {
        $ini = <<<INI
db_driver = "mysql"
db_host   = "localhost"
db_name   = "my_database"
db_user   = "root"
db_pass   = ""
db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $this->assertEquals(
            'mysql:host=localhost;dbname=my_database', $c->getItem('db_dsn')
        );
    }

    public function testInstantiator()
    {
        $ini = <<<INI
[foo stdClass]
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $instantiator = $c->getItem('foo', true);
        $this->assertEquals('stdClass', $instantiator->getClassName());
    }

    public function testInstantiatorParams()
    {
        $ini = <<<INI
[foo stdClass]
foo = bar
baz = bat
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $instantiator = $c->getItem('foo', true);
        $this->assertEquals('bar', $instantiator->getParam('foo'));
        $this->assertEquals('bat', $instantiator->getParam('baz'));
    }

    public function testInstantiatorParamsArray()
    {
        $ini = <<<INI
[foo stdClass]
foo[abc] = bar
foo[def] = bat
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $instantiator = $c->getItem('foo', true);
        $expected = array(
            'abc' => 'bar',
            'def' => 'bat'
        );
        $this->assertEquals($expected, $instantiator->getParam('foo'));
    }

    public function testInstantiatorParamsBrackets()
    {
        $ini = <<<INI
[foo stdClass]
foo[abc] = [bat, blz]
foo[def] = bat
baz = [bat, blz]
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $instantiator = $c->getItem('foo', true);
        $expectedFoo = array(
            'abc' => array('bat', 'blz'),
            'def' => 'bat'
        );
        $expectedBaz = array('bat', 'blz');
        $this->assertEquals($expectedFoo, $instantiator->getParam('foo'));
        $this->assertEquals($expectedBaz, $instantiator->getParam('baz'));
    }

    public function testInstanasdkets()
    {
        $ini = <<<INI
db_driver = "mysql"
db_host   = "localhost"
db_name   = "new_guide"
db_user   = "root"
db_pass   = ""
db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"

smtp_host             = "smtp.gmail.com"

[smtp_config]
auth     = "login"
username = "foo"
password = "bar"
ssl      = "ssl"
port     = "495"


[connection PDO]
getAvailableDrivers[] =
dsn            = [db_dsn]
username       = [db_user]
password       = [db_pass]
setAttribute[] = [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTIONS]
setAttribute[] = [PDO::ATTR_CASE, PDO::CASE_NORMAL]
exec[]         = "SET NAMES UTF-8"
exec[]         = "SET CHARSET UTF-8"


INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        print_r($c);
        print_r($c->connection);
    }

}