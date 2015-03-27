<?php
namespace Respect\Config;

use Respect\Test\StreamWrapper;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!in_array($this->getName(), array('testLoadFile', 'testLoadFileMultiple')))
            return;

        $ini = <<<INI
foo = bar
baz = bat
INI;
        $pnd = <<<PND
happy = panda
panda = happy
PND;
        StreamWrapper::setStreamOverrides(array(
            'exists.ini' => $ini,
            'multiple/foo-bar-baz.ini' => $ini,
            'multiple/happy-panda.ini' => $pnd,
        ));
    }

    public function tearDown() {
        StreamWrapper::releaseOverrides();
    }

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

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid input. Must be a valid file or array
     */
    public function testConfigure() {
        $c = new Container(1);
        $c->a;
    }

    public function testLoadInvalid()
    {
        $this->setExpectedException('InvalidArgumentException');
        $c = new Container('inexistent.ini');
        $c->foo;
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
lorem = ["foo"DIRECTORY_SEPARATOR"bar", PATH_SEPARATOR]
ipsum = [PATH_SEPARATOR, "foo"DIRECTORY_SEPARATOR"bar"]
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $this->assertEquals(E_USER_ERROR, $c->foo);
        $this->assertEquals(\PDO::ATTR_ERRMODE, $c->bar);
        $this->assertEquals(array(E_USER_ERROR, E_USER_WARNING), $c->faa);
        $this->assertEquals(array(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION), $c->bor);
        $this->assertEquals(array("foo".DIRECTORY_SEPARATOR."bar", PATH_SEPARATOR), $c->lorem);
        $this->assertEquals(array(PATH_SEPARATOR, "foo".DIRECTORY_SEPARATOR."bar"), $c->ipsum);
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

    public function testClosureWithLoadedFile()
    {
        $ini = <<<INI
respect_blah = ""
INI;
        $c = new Container($ini);
        $c->panda = function() { return 'ok'; };
        $this->assertEquals('ok', $c->getItem('panda', false));
    }

    public function testLazyLoadinessOnMultipleConfigLevels()
    {
        $GLOBALS['_SHIT_'] = false;
        $ini = <<<INI
[foo Respect\Config\WheneverIBornIPopulateAGlobalCalled_SHIT_]
child = ""
[bar Respect\Config\WheneverIBornIPopulateAGlobalCalled_SHIT_]
child = [foo]
[baz Respect\Config\WheneverIBornIPopulateAGlobalCalled_SHIT_]
child = [bar]
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $this->assertFalse($GLOBALS['_SHIT_']);
        $GLOBALS['_SHIT_'] = false;
    }

    public function testSequencesConstructingLazy()
    {
        $ini = <<<INI
[bar Respect\Config\Bar]
[foo Respect\Config\Foo]
hello[] = ["opa", [bar]]
INI;
        $c = new Container();
        $c->loadArray(parse_ini_string($ini, true));
        $this->assertInstanceOf('Respect\Config\Bar', $c->foo->bar);
    }

    public function testPascutti()
    {
        $GLOBALS['_SHIT_'] = false;
        $ini = <<<INI
[pdo StdClass]

[db Respect\Config\DatabaseWow]
con = [pdo];
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $c->pdo = new \PDO('sqlite::memory:');
        $this->assertSame($c->pdo, $c->db->c);
    }

    public function testPascuttiTypeHintIssue40()
    {
        $GLOBALS['_MERD_'] = false;
        $ini = <<<INI
[now DateTime]

[typed Respect\Config\TypeHintWowMuchType]
date = [now];
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $this->assertInstanceOf(
            'Respect\Config\TypeHintWowMuchType',
            $c->typed
        );
    }

    public function testLockedContainer()
    {
        $ini = <<<INI
foo = [undef]
bar = [foo]
INI;
        $c = new Container(parse_ini_string($ini, true));
        $result = $c(array('undef'=>'Hello'));
        $this->assertEquals('Hello', $result->bar);
    }
    public function testLockedContainer2()
    {
        $ini = <<<INI
foo = [undef]
bar = [foo]
INI;
        $c = new Container(parse_ini_string($ini, true));
        $result = $c->bar(array('undef'=>'Hello'));
        $this->assertEquals('Hello', $result);
    }
    public function testFactory()
    {
        $ini = <<<INI
[now new DateTime]
time = now
INI;
        $c = new Container(parse_ini_string($ini, true));
        $result = $c->now;
        $result2 = $c->now;
        $this->assertNotSame($result, $result2);
    }
    public function testDependenciesDoesNotAffectFactories()
    {
        $ini = <<<INI
[now DateTime]
time = now
INI;
        $c = new Container(parse_ini_string($ini, true));
        $result = $c->now;
        $result2 = $c->now;
        $this->assertSame($result, $result2);
    }
    public function testByInstanceCallback()
    {
        $ini = <<<INI
[instanceof DateTime]
time = now
INI;
        $c = new Container(parse_ini_string($ini, true));
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
    public function testByInstanceCallback2()
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
    public function testByMethodCallback()
    {
        $c = new Container();
        $c(new \DateTime);
        $result = $c(array(__NAMESPACE__.'\\Foo', 'hey'));
        $this->assertInstanceOf('DateTime', $result);
    }


    public function testClassConstants()
    {
        $ini = <<<INI
foo = \Respect\Config\TestConstant::CONS_TEST
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $this->assertEquals(\Respect\Config\TestConstant::CONS_TEST, $c->foo);
    }

    public function testClassConstantsAnotherNamespace()
    {
        class_alias('Respect\Config\TestConstant', 'Respect\Test\Another\Cons');
        $ini = <<<INI
foo = \Respect\Test\Another\Cons::CONS_TEST
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $this->assertEquals(\Respect\Test\Another\Cons::CONS_TEST, $c->foo);
    }


    public function testInstantiatorWithUnderline()
    {
        $ini = <<<INI
[foo_bar stdClass]
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $instantiator = $c->getItem('foo_bar', true);
        $this->assertEquals('stdClass', $instantiator->getClassName());
    }

    public function testClassWithAnotherAndUnderline()
    {
        $ini = <<<INI
[foo_bar stdClass]

[bar_foo \Respect\Config\WheneverWithAProperty]
test = [foo_bar]
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $this->assertEquals(get_class($c->foo_bar), get_class($c->bar_foo->test));
    }

}
class Bar {}
class Foo
{
    function hey(\DateTime $date) {
       return $date;
    }

    function hello($some, Bar $bar) {
        $this->bar = $bar;
    }
}

class WheneverIBornIPopulateAGlobalCalled_SHIT_
{
    public function __construct(){
        $GLOBALS['_SHIT_'] = true;
    }
}

class DatabaseWow {
    public $c;
    public function __construct($con) {
        $this->c = $con;
    }
}


class TypeHintWowMuchType {
    public $d;
    public function __construct(\DateTime $date) {
        $this->d = $date;
    }
}

class TestConstant {
    const CONS_TEST = "XPTO";
}


class WheneverWithAProperty
{
    public $test;
    
}
