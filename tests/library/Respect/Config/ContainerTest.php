<?php

namespace Respect\Config;

function file_exists($name) { //override for testing
    return $name == 'exists.ini';
}

function is_file($name) { //override for testing
    return $name == 'exists.ini';
}

function parse_ini_file() { //override for testing
        return array('foo'=>'bar', 'baz'=>'bat');
}

class ContainerTest extends \PHPUnit_Framework_TestCase
{

    public function testLoadArray()
    {
        $ini = <<<INI
foo = bar
baz = bat
INI;
        $c = new Container(parse_ini_string($ini, true));
        $this->assertTrue(isset($c->foo));
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }
    public function testLoadFile()
    {
        $c = new Container('exists.ini');
        $this->assertTrue(isset($c->foo));
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testLoadInvalid()
    {
        $this->setExpectedException('InvalidArgumentException');
        $c = new Container('inexistent.ini');
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

    public function testInstantiator2()
    {
        $ini = <<<INI
foo stdClass =
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $instantiator = $c->getItem('foo', true);
        $this->assertEquals('stdClass', $instantiator->getClassName());
    }

    public function testConstants()
    {
        $ini = <<<INI
foo = E_USER_ERROR
faa = [E_USER_ERROR, E_USER_WARNING]
bar = PDO::ATTR_ERRMODE
bor = [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION]
zed = DIRECTORY_SEPARATOR'usr'
zod = [DIRECTORY_SEPARATOR'usr', DIRECTORY_SEPARATOR'var']
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $this->assertEquals(E_USER_ERROR, $c->foo);
        $this->assertEquals(array(E_USER_ERROR, E_USER_WARNING), $c->faa);
        $this->assertEquals(array(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION), $c->bor);
        $this->assertEquals(DIRECTORY_SEPARATOR.'usr', $c->zed);
        $this->assertEquals(array(DIRECTORY_SEPARATOR.'usr', DIRECTORY_SEPARATOR.'var'), $c->zod);
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
    public function testInstantiatorMethodCalls()
    {
        $ini = <<<INI
[date DateTime]
setTimestamp[] = 123
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $dateTime = $c->date;
    }
    public function testInstantiatorNullMethodCalls()
    {
        $ini = <<<INI
[conn PDO]
dsn = sqlite::memory:
beginTransaction[] =
query[] = "CREATE TABLE foo(id INT)"
commit[] =
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $conn = $c->conn;
        $this->assertNotEmpty($conn->query('SELECT * FROM sqlite_master')->fetch());
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
    public function testInstantiatorParamsBracketsReferences()
    {
        $ini = <<<INI
hi = someName
[foo stdClass]
foo[abc] = [bat, blz]
foo[def] = bat
baz = [bat, [hi]]
barr = [bat, [hi]]
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $instantiator = $c->getItem('foo', true);
        $expectedFoo = array(
            'abc' => array('bat', 'blz'),
            'def' => 'bat'
        );
        $expectedBaz = array('bat', 'someName');
        $this->assertEquals($expectedFoo, $instantiator->getParam('foo'));
        $this->assertEquals($expectedBaz, $instantiator->getParam('baz'));
    }
    
    public function testGetItemLazyLoad()
    {
        $c = new Container;
        $c->foo = function() { return 'ok'; };
        $this->assertEquals('ok', $c->getItem('foo', false));
    }

}