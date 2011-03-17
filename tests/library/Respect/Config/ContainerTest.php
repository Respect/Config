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

}