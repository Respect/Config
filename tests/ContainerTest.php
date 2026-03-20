<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;
use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use stdClass;

use function chdir;
use function class_alias;
use function extension_loaded;
use function file_get_contents;
use function get_class;
use function getcwd;
use function in_array;
use function is_dir;
use function parse_ini_string;

use const DIRECTORY_SEPARATOR;
use const E_USER_ERROR;
use const E_USER_WARNING;
use const PATH_SEPARATOR;

#[CoversClass(Container::class)]
final class ContainerTest extends TestCase
{
    private string $originalCwd;

    private string $vfsRoot;

    protected function setUp(): void
    {
        $cwd = getcwd();
        self::assertIsString($cwd);
        $this->originalCwd = $cwd;

        $ini = <<<'INI'
foo = bar
baz = bat
INI;
        $pnd = <<<'PND'
happy = panda
panda = happy
PND;
        $structure = [
            'exists.ini' => $ini,
            'multiple' => [
                'foo-bar-baz.ini' => $ini,
                'happy-panda.ini' => $pnd,
            ],
        ];

        vfsStream::setup('root', null, $structure);
        $this->vfsRoot = vfsStream::url('root');
    }

    public function testLoadArray(): void
    {
        $ini = <<<'INI'
foo = bar
baz = bat
INI;
        $c = new Container(self::parseIni($ini));
        $this->assertTrue($c->has('foo'));
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testLoadFile(): void
    {
        $contents = file_get_contents($this->vfsRoot . '/exists.ini');
        $c = new Container($contents);
        $this->assertTrue($c->has('foo'));
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testContainerInterop(): void
    {
        $ini = <<<'INI'
foo = bar
baz = bat
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $this->assertTrue($c->has('foo'));
        $this->assertEquals('bar', $c->get('foo'));
        $this->assertEquals('bat', $c->get('baz'));
    }

    public function testLoadInvalidName(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Item baz not found');
        $ini = <<<'INI'
foo = bar
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $c->get('baz');
    }

    public function testConfigure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input. Must be a valid file or array');
        $c = new Container(1);
        $c->get('a');
    }

    public function testLoadInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $c = new Container('inexistent.ini');
        $c->get('foo');
    }

    public function testLoadArraySections(): void
    {
        $ini = <<<'INI'
[sec]
foo = bar
baz = bat
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $d = $c->getItem('sec');
        $this->assertEquals('bar', $d['foo']);
        $this->assertEquals('bat', $d['baz']);
    }

    public function testExpandVars(): void
    {
        $ini = <<<'INI'
db_driver = "mysql"
db_host   = "localhost"
db_name   = "my_database"
db_user   = "root"
db_pass   = ""
db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $this->assertEquals(
            'mysql:host=localhost;dbname=my_database',
            $c->getItem('db_dsn'),
        );
    }

    public function testInstantiator(): void
    {
        $ini = <<<'INI'
[foo \stdClass]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $instantiator = $c->getItem('foo', true);
        $this->assertEquals('\stdClass', $instantiator->getClassName());
    }

    public function testInstantiator2(): void
    {
        $ini = <<<'INI'
foo \stdClass =
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $instantiator = $c->getItem('foo', true);
        $this->assertEquals('\stdClass', $instantiator->getClassName());
    }

    public function testConstants(): void
    {
        $ini = <<<'INI'
foo = E_USER_ERROR
faa = [E_USER_ERROR, E_USER_WARNING]
bar = PDO::ATTR_ERRMODE
bor = [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION]
lorem = ["foo"DIRECTORY_SEPARATOR"bar", PATH_SEPARATOR]
ipsum = [PATH_SEPARATOR, "foo"DIRECTORY_SEPARATOR"bar"]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $this->assertEquals(E_USER_ERROR, $c->getItem('foo'));
        $this->assertEquals(PDO::ATTR_ERRMODE, $c->getItem('bar'));
        $this->assertEquals([E_USER_ERROR, E_USER_WARNING], $c->getItem('faa'));
        $this->assertEquals([PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION], $c->getItem('bor'));
        $this->assertEquals(['foo' . DIRECTORY_SEPARATOR . 'bar', PATH_SEPARATOR], $c->getItem('lorem'));
        $this->assertEquals([PATH_SEPARATOR, 'foo' . DIRECTORY_SEPARATOR . 'bar'], $c->getItem('ipsum'));
    }

    public function testInstantiatorParams(): void
    {
        $ini = <<<'INI'
[foo stdClass]
foo = bar
baz = bat
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $instantiator = $c->getItem('foo', true);
        $this->assertEquals('bar', $instantiator->getParam('foo'));
        $this->assertEquals('bat', $instantiator->getParam('baz'));
    }

    public function testInstantiatorMethodCalls(): void
    {
        $ini = <<<'INI'
[date DateTime]
setTimestamp[] = 123
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $dateTime = $c->getItem('date');
        $this->assertEquals(123, $dateTime->getTimestamp());
    }

    public function testInstantiatorNullMethodCalls(): void
    {
        if (!extension_loaded('pdo') || !in_array('sqlite', PDO::getAvailableDrivers())) {
            $this->markTestSkipped('SQLite PDO driver not available');
        }

        $ini = <<<'INI'
[conn \PDO]
dsn = sqlite::memory:
beginTransaction[] =
query[] = "CREATE TABLE foo(id INT)"
commit[] =
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $conn = $c->getItem('conn');
        $this->assertNotEmpty($conn->query('SELECT * FROM sqlite_master')->fetch());
    }

    public function testInstantiatorParamsArray(): void
    {
        $ini = <<<'INI'
[foo stdClass]
foo[abc] = bar
foo[def] = bat
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $instantiator = $c->getItem('foo', true);
        $expected = [
            'abc' => 'bar',
            'def' => 'bat',
        ];
        $this->assertEquals($expected, $instantiator->getParam('foo'));
    }

    public function testInstantiatorParamsBrackets(): void
    {
        $ini = <<<'INI'
[foo stdClass]
foo[abc] = [bat, blz]
foo[def] = bat
baz = [bat, blz]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $instantiator = $c->getItem('foo', true);
        $expectedFoo = [
            'abc' => ['bat', 'blz'],
            'def' => 'bat',
        ];
        $expectedBaz = ['bat', 'blz'];
        $this->assertEquals($expectedFoo, $instantiator->getParam('foo'));
        $this->assertEquals($expectedBaz, $instantiator->getParam('baz'));
    }

    public function testInstantiatorParamsBracketsReferences(): void
    {
        $ini = <<<'INI'
hi = someName
[foo stdClass]
foo[abc] = [bat, blz]
foo[def] = bat
baz = [bat, [hi]]
barr = [bat, [hi]]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $instantiator = $c->getItem('foo', true);
        $expectedFoo = [
            'abc' => ['bat', 'blz'],
            'def' => 'bat',
        ];
        $expectedBaz = ['bat', 'someName'];
        $this->assertEquals($expectedFoo, $instantiator->getParam('foo'));
        $this->assertEquals($expectedBaz, $instantiator->getParam('baz'));
    }

    public function testGetItemLazyLoad(): void
    {
        $c = new Container();
        $c['foo'] = static function () {
            return 'ok';
        };
        $this->assertEquals('ok', $c->getItem('foo', false));
    }

    public function testClosureWithLoadedFile(): void
    {
        $ini = <<<'INI'
respect_blah = ""
INI;
        $c = new Container($ini);
        $c['panda'] = static function () {
            return 'ok';
        };
        $this->assertEquals('ok', $c->getItem('panda', false));
    }

    public function testLazyLoadinessOnMultipleConfigLevels(): void
    {
        $GLOBALS['_SHIT_'] = false;
        $ini = <<<'INI'
[foo Respect\Config\WheneverIBornIPopulateAGlobalCalled_SHIT_]
child = ""
[bar Respect\Config\WheneverIBornIPopulateAGlobalCalled_SHIT_]
child = [foo]
[baz Respect\Config\WheneverIBornIPopulateAGlobalCalled_SHIT_]
child = [bar]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $this->assertFalse($GLOBALS['_SHIT_']);
        $GLOBALS['_SHIT_'] = false;
    }

    public function testSequencesConstructingLazy(): void
    {
        $ini = <<<'INI'
[bar Respect\Config\Bar]
[foo Respect\Config\Foo]
hello[] = ["opa", [bar]]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $foo = $c->getItem('foo');
        $this->assertInstanceOf(Bar::class, $foo->bar);
    }

    public function testPascutti(): void
    {
        if (!extension_loaded('pdo') || !in_array('sqlite', PDO::getAvailableDrivers())) {
            $this->markTestSkipped('SQLite PDO driver not available');
        }

        $GLOBALS['_SHIT_'] = false;
        $ini = <<<'INI'
[pdo StdClass]

[db Respect\Config\DatabaseWow]
con = [pdo];
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        // __set replaces the Instantiator's pending instance
        $c->pdo = new PDO('sqlite::memory:');
        $this->assertSame($c->getItem('pdo'), $c->getItem('db')->c);
    }

    public function testPascuttiTypeHintIssue40(): void
    {
        $GLOBALS['_MERD_'] = false;
        $ini = <<<'INI'
[now DateTime]

[typed Respect\Config\TypeHintWowMuchType]
date = [now];
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $this->assertInstanceOf(
            TypeHintWowMuchType::class,
            $c->getItem('typed'),
        );
    }

    public function testLockedContainer(): void
    {
        $ini = <<<'INI'
foo = [undef]
bar = [foo]
INI;
        $c = new Container(self::parseIni($ini));
        $result = $c(['undef' => 'Hello']);
        $this->assertEquals('Hello', $result->getItem('bar'));
    }

    public function testLockedContainer2(): void
    {
        $ini = <<<'INI'
foo = [undef]
bar = [foo]
INI;
        $c = new Container(self::parseIni($ini));
        $c(['undef' => 'Hello']);
        $result = $c->getItem('bar');
        $this->assertEquals('Hello', $result);
    }

    public function testFactory(): void
    {
        $ini = <<<'INI'
[now new DateTime]
datetime = now
INI;
        $c = new Container(self::parseIni($ini));
        $result = $c->getItem('now');
        $result2 = $c->getItem('now');
        $this->assertNotSame($result, $result2);
    }

    public function testDependenciesDoesNotAffectFactories(): void
    {
        $ini = <<<'INI'
[now DateTime]
datetime = now
INI;
        $c = new Container(self::parseIni($ini));
        $result = $c->getItem('now');
        $result2 = $c->getItem('now');
        $this->assertSame($result, $result2);
    }

    public function testByInstanceCallback(): void
    {
        $ini = <<<'INI'
[instanceof DateTime]
datetime = now
INI;
        $c = new Container(self::parseIni($ini));
        $called = false;
        $result = $c(static function (DateTime $date) use (&$called) {
            $called = true;

            return $date;
        });
        $result2 = $c['DateTime'];
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertInstanceOf(DateTime::class, $result2);
        $this->assertTrue($called);
    }

    public function testByInstanceCallback2(): void
    {
        $c = new Container();
        $c(new DateTime());
        $called = false;
        $result = $c(static function (DateTime $date) use (&$called) {
            $called = true;

            return $date;
        });
        $result2 = $c['DateTime'];
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertInstanceOf(DateTime::class, $result2);
        $this->assertTrue($called);
    }

    public function testByMethodCallback(): void
    {
        $c = new Container();
        $c(new DateTime());
        $result = $c([__NAMESPACE__ . '\\Foo', 'hey']);
        $this->assertInstanceOf(DateTime::class, $result);
    }

    public function testClassConstants(): void
    {
        $ini = <<<'INI'
foo = \Respect\Config\TestConstant::CONS_TEST
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $this->assertEquals(TestConstant::CONS_TEST, $c->getItem('foo'));
    }

    public function testClassConstantsAnotherNamespace(): void
    {
        class_alias(TestConstant::class, 'Respect\Test\Another\Cons');
        $ini = <<<'INI'
foo = \Respect\Test\Another\Cons::CONS_TEST
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        // The container resolves the aliased constant at runtime
        $this->assertEquals(TestConstant::CONS_TEST, $c->getItem('foo'));
    }

    public function testInstantiatorWithUnderline(): void
    {
        $ini = <<<'INI'
[foo_bar \stdClass]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $instantiator = $c->getItem('foo_bar', true);
        $this->assertEquals('\stdClass', $instantiator->getClassName());
    }

    public function testClassWithAnotherAndUnderline(): void
    {
        $ini = <<<'INI'
[foo_bar stdClass]

[bar_foo \Respect\Config\WheneverWithAProperty]
test = [foo_bar]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $this->assertEquals(get_class($c->getItem('foo_bar')), get_class($c->getItem('bar_foo')->test));
    }

    public function testIsset(): void
    {
        $ini = <<<'INI'
foo = bar
INI;
        $c = new Container(self::parseIni($ini));
        $this->assertTrue(isset($c->foo));
        $this->assertFalse(isset($c->nonexistent));
    }

    public function testSetMethod(): void
    {
        $c = new Container();
        $c->set('key', 'value');
        $this->assertEquals('value', $c->getItem('key'));
    }

    public function testMagicGet(): void
    {
        $ini = <<<'INI'
foo = bar
INI;
        $c = new Container(self::parseIni($ini));
        $this->assertEquals('bar', $c->__get('foo'));
    }

    public function testMagicCall(): void
    {
        $ini = <<<'INI'
foo = [undef]
bar = [foo]
INI;
        $c = new Container(self::parseIni($ini));
        $result = $c->__call('bar', [['undef' => 'Hello']]);
        $this->assertEquals('Hello', $result);
    }

    public function testLoadString(): void
    {
        $ini = <<<'INI'
foo = bar
baz = bat
INI;
        $c = new Container();
        $c->loadString($ini);
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testLoadStringInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid configuration string');
        $c = new Container();
        $c->loadString('');
    }

    public function testDeferredConfigWithArray(): void
    {
        $c = new Container(['foo' => 'bar']);
        $this->assertEquals('bar', $c->getItem('foo'));
    }

    public function testDeferredConfigWithIniString(): void
    {
        $c = new Container("foo = bar\nbaz = bat");
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testDeferredConfigWithFile(): void
    {
        $c = new Container($this->vfsRoot . '/exists.ini');
        $this->assertEquals('bar', $c->getItem('foo'));
    }

    public function testHasReturnsFalseForNonExistentClass(): void
    {
        $ini = <<<'INI'
[foo Respect\Config\NonExistentClass12345]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $this->assertFalse($c->has('foo'));
    }

    public function testHasReturnsTrueForValidInstantiator(): void
    {
        $ini = <<<'INI'
[foo DateTime]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $this->assertTrue($c->has('foo'));
    }

    public function testGetItemRawReturnsInstantiator(): void
    {
        $ini = <<<'INI'
[foo DateTime]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $raw = $c->getItem('foo', true);
        $this->assertInstanceOf(Instantiator::class, $raw);
    }

    public function testClosureReceivesContainer(): void
    {
        $c = new Container();
        $c['greeting'] = 'hello';
        $c['result'] = static function (Container $container) {
            return $container['greeting'] . ' world';
        };
        $this->assertEquals('hello world', $c->getItem('result'));
    }

    public function testInstanceofSyntax(): void
    {
        $ini = <<<'INI'
[instanceof DateTime]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $this->assertInstanceOf(DateTime::class, $c->getItem('DateTime'));
    }

    public function testLoadMultipleArraysMergesState(): void
    {
        $c = new Container();
        $c->loadArray(self::parseIni('foo = bar'));
        $c->loadArray(self::parseIni('baz = bat'));
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testVariableExpansionInSequence(): void
    {
        $ini = <<<'INI'
name = world
greetings = [hello, [name]]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $result = $c->getItem('greetings');
        $this->assertEquals(['hello', 'world'], $result);
    }

    public function testLoadFileInvalidIni(): void
    {
        $vfs = vfsStream::setup('bad');
        vfsStream::newFile('unreadable.ini', 0000)->at($vfs)->setContent('foo = bar');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid configuration INI file');
        $c = new Container();
        @$c->loadFile(vfsStream::url('bad') . '/unreadable.ini');
    }

    public function testLoadArrayWithInstantiatorValue(): void
    {
        $i = new Instantiator('stdClass');
        $i->setParam('foo', 'bar');
        $c = new Container();
        $c->loadArray(['myobj' => $i]);
        $result = $c->getItem('myobj');
        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertEquals('bar', $result->foo);
    }

    public function testLoadArrayWithClosureValue(): void
    {
        $c = new Container();
        $c->loadArray(['fn' => static fn() => 'result']);
        $this->assertEquals('result', $c->getItem('fn'));
    }

    public function testGetItemWrapsReflectionException(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('not found');
        $c = new Container();
        $i = new Instantiator(PrivateConstructorClass::class);
        $i->setParam('__construct', ['x']);
        $c['broken'] = $i;
        $c->getItem('broken');
    }

    public function testUnknownInstantiatorModifier(): void
    {
        $ini = <<<'INI'
[foo unknown stdClass]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $raw = $c->getItem('foo', true);
        $this->assertInstanceOf(Instantiator::class, $raw);
        $this->assertEquals('unknown stdClass', $raw->getClassName());
    }

    public function testOffsetSetWithAutowire(): void
    {
        $c = new Container();
        $autowire = new Autowire(stdClass::class);
        $c['myobj'] = $autowire;
        $result = $c->getItem('myobj');
        $this->assertInstanceOf(stdClass::class, $result);
    }

    public function testInvokeCallbackWithUntypedParam(): void
    {
        $c = new Container();
        $c(new DateTime());
        $result = $c(static function ($untyped, DateTime $date) {
            return [$untyped, $date];
        });
        $this->assertNull($result[0]);
        $this->assertInstanceOf(DateTime::class, $result[1]);
    }

    public function testParseValuePassesInstantiatorThrough(): void
    {
        $inner = new Instantiator('stdClass');
        $inner->setParam('x', 'y');
        $c = new Container();
        $c->loadArray(['outer stdClass' => ['child' => $inner]]);
        $result = $c->getItem('outer');
        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertInstanceOf(stdClass::class, $result->child);
        $this->assertEquals('y', $result->child->x);
    }

    public function testAutowireModifierInContainerCreatesAutowire(): void
    {
        $ini = <<<'INI'
[dep Respect\Config\WheneverWithAProperty]
test = hello

[consumer autowire Respect\Config\WheneverWithAProperty]
INI;
        $c = new Container();
        $c->loadArray(self::parseIni($ini));
        $raw = $c->getItem('consumer', true);
        $this->assertInstanceOf(Autowire::class, $raw);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->originalCwd)) {
            return;
        }

        chdir($this->originalCwd);
    }

    /** @return array<string, mixed> */
    private static function parseIni(string $ini): array
    {
        $result = parse_ini_string($ini, true);
        self::assertIsArray($result);

        return $result;
    }
}

class Bar
{
}

class Foo
{
    public mixed $bar = null;

    public static function hey(DateTime $date): DateTime
    {
        return $date;
    }

    public function hello(mixed $some, Bar $bar): void
    {
        $this->bar = $bar;
    }
}

class WheneverIBornIPopulateAGlobalCalled_SHIT_
{
    public function __construct()
    {
        $GLOBALS['_SHIT_'] = true;
    }
}

class DatabaseWow
{
    public mixed $c;

    public function __construct(mixed $con)
    {
        $this->c = $con;
    }
}

class TypeHintWowMuchType
{
    public DateTime $d;

    public function __construct(DateTime $date)
    {
        $this->d = $date;
    }
}

class TestConstant
{
    public const string CONS_TEST = 'XPTO';
}

class WheneverWithAProperty
{
    public mixed $test = null;
}

class PrivateConstructorClass
{
    public string $value = '';

    private function __construct(string $x)
    {
        $this->value = $x;
    }
}
