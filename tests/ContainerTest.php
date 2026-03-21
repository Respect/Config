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

    public function testConstructorWithArray(): void
    {
        $c = new Container(['foo' => 'bar', 'baz' => 'bat']);
        $this->assertTrue($c->has('foo'));
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testLoadViaIniLoader(): void
    {
        $c = IniLoader::load(self::parseIni("foo = bar\nbaz = bat"));
        $this->assertTrue($c->has('foo'));
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testLoadViaIniLoaderString(): void
    {
        $c = IniLoader::load("foo = bar\nbaz = bat");
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testLoadViaIniLoaderFile(): void
    {
        $c = IniLoader::load($this->vfsRoot . '/exists.ini');
        $this->assertEquals('bar', $c->getItem('foo'));
    }

    public function testContainerInterop(): void
    {
        $ini = <<<'INI'
foo = bar
baz = bat
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $c->get('baz');
    }

    public function testLoadInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input. Must be a valid file or array');
        IniLoader::load(1);
    }

    public function testLoadInvalidIniString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        IniLoader::load('inexistent.ini');
    }

    public function testLoadArraySections(): void
    {
        $ini = <<<'INI'
[sec]
foo = bar
baz = bat
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $instantiator = $c->getItem('foo', true);
        $this->assertEquals('\stdClass', $instantiator->getClassName());
    }

    public function testInstantiator2(): void
    {
        $ini = <<<'INI'
foo \stdClass =
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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

    public function testClosureWithIniLoad(): void
    {
        $ini = <<<'INI'
respect_blah = ""
INI;
        $c = IniLoader::load($ini);
        $c['panda'] = static function () {
            return 'ok';
        };
        $this->assertEquals('ok', $c->getItem('panda', false));
    }

    public function testLazyLoadinessOnMultipleConfigLevels(): void
    {
        $GLOBALS['_SIDE_EFFECT_'] = false;
        $ini = <<<'INI'
[foo Respect\Config\SideEffectOnConstruct]
child = ""
[bar Respect\Config\SideEffectOnConstruct]
child = [foo]
[baz Respect\Config\SideEffectOnConstruct]
child = [bar]
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $this->assertFalse($GLOBALS['_SIDE_EFFECT_']);
        $GLOBALS['_SIDE_EFFECT_'] = false;
    }

    public function testSequencesConstructingLazy(): void
    {
        $ini = <<<'INI'
[bar Respect\Config\Bar]
[foo Respect\Config\Foo]
hello[] = ["opa", [bar]]
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $foo = $c->getItem('foo');
        $this->assertInstanceOf(Bar::class, $foo->bar);
    }

    public function testPascutti(): void
    {
        if (!extension_loaded('pdo') || !in_array('sqlite', PDO::getAvailableDrivers())) {
            $this->markTestSkipped('SQLite PDO driver not available');
        }

        $GLOBALS['_SIDE_EFFECT_'] = false;
        $ini = <<<'INI'
[pdo StdClass]

[db Respect\Config\DatabaseWow]
con = [pdo];
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        // __set replaces the Instantiator's pending instance
        $c->pdo = new PDO('sqlite::memory:');
        $this->assertSame($c->getItem('pdo'), $c->getItem('db')->c);
    }

    public function testPascuttiTypeHintIssue40(): void
    {
        $ini = <<<'INI'
[now DateTime]

[typed Respect\Config\TypeHintWowMuchType]
date = [now];
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $this->assertInstanceOf(
            TypeHintWowMuchType::class,
            $c->getItem('typed'),
        );
    }

    public function testPrePopulatedContainer(): void
    {
        $ini = <<<'INI'
foo = [undef]
bar = [foo]
INI;
        $c = new Container(['undef' => 'Hello']);
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $this->assertEquals('Hello', $c->getItem('bar'));
    }

    public function testPrePopulatedContainer2(): void
    {
        $ini = <<<'INI'
foo = [undef]
bar = [foo]
INI;
        $c = new Container(['undef' => 'Hello']);
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $result = $c->getItem('bar');
        $this->assertEquals('Hello', $result);
    }

    public function testFactory(): void
    {
        $ini = <<<'INI'
[now new DateTime]
datetime = now
INI;
        $c = IniLoader::load(self::parseIni($ini));
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
        $c = IniLoader::load(self::parseIni($ini));
        $result = $c->getItem('now');
        $result2 = $c->getItem('now');
        $this->assertSame($result, $result2);
    }

    public function testClassConstants(): void
    {
        $ini = <<<'INI'
foo = \Respect\Config\TestConstant::CONS_TEST
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $this->assertEquals(TestConstant::CONS_TEST, $c->getItem('foo'));
    }

    public function testClassConstantsAnotherNamespace(): void
    {
        class_alias(TestConstant::class, 'Respect\Test\Another\Cons');
        $ini = <<<'INI'
foo = \Respect\Test\Another\Cons::CONS_TEST
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        // The container resolves the aliased constant at runtime
        $this->assertEquals(TestConstant::CONS_TEST, $c->getItem('foo'));
    }

    public function testInstantiatorWithUnderline(): void
    {
        $ini = <<<'INI'
[foo_bar \stdClass]
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $this->assertEquals(get_class($c->getItem('foo_bar')), get_class($c->getItem('bar_foo')->test));
    }

    public function testIsset(): void
    {
        $c = new Container(['foo' => 'bar']);
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
        $c = new Container(['foo' => 'bar']);
        $this->assertEquals('bar', $c->__get('foo'));
    }

    public function testLoadString(): void
    {
        $ini = <<<'INI'
foo = bar
baz = bat
INI;
        $c = new Container();
        (new IniLoader($c))->fromString($ini);
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testLoadStringInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid configuration string');
        $c = new Container();
        (new IniLoader($c))->fromString('');
    }

    public function testHasReturnsFalseForNonExistentClass(): void
    {
        $ini = <<<'INI'
[foo Respect\Config\NonExistentClass12345]
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $this->assertFalse($c->has('foo'));
    }

    public function testHasReturnsTrueForValidInstantiator(): void
    {
        $ini = <<<'INI'
[foo DateTime]
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $this->assertTrue($c->has('foo'));
    }

    public function testGetItemRawReturnsInstantiator(): void
    {
        $ini = <<<'INI'
[foo DateTime]
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $this->assertInstanceOf(DateTime::class, $c->getItem('DateTime'));
    }

    public function testLoadMultipleArraysMergesState(): void
    {
        $c = new Container();
        $loader = new IniLoader($c);
        $loader->fromArray(self::parseIni('foo = bar'));
        $loader->fromArray(self::parseIni('baz = bat'));
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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
        @(new IniLoader($c))->fromFile(vfsStream::url('bad') . '/unreadable.ini');
    }

    public function testLoadArrayWithInstantiatorValue(): void
    {
        $i = new Instantiator('stdClass');
        $i->setParam('foo', 'bar');
        $c = new Container();
        (new IniLoader($c))->fromArray(['myobj' => $i]);
        $result = $c->getItem('myobj');
        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertEquals('bar', $result->foo);
    }

    public function testLoadArrayWithClosureValue(): void
    {
        $c = new Container();
        (new IniLoader($c))->fromArray(['fn' => static fn() => 'result']);
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
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

    public function testParseValuePassesInstantiatorThrough(): void
    {
        $inner = new Instantiator('stdClass');
        $inner->setParam('x', 'y');
        $c = new Container();
        (new IniLoader($c))->fromArray(['outer stdClass' => ['child' => $inner]]);
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
        (new IniLoader($c))->fromArray(self::parseIni($ini));
        $raw = $c->getItem('consumer', true);
        $this->assertInstanceOf(Autowire::class, $raw);
    }

    public function testPlainCallableIsCached(): void
    {
        $count = 0;
        $callable = new class ($count) {
            public function __construct(private int &$count)
            {
            }

            public function __invoke(): int
            {
                return ++$this->count;
            }
        };
        $c = new Container();
        $c['counter'] = [$callable, '__invoke'];
        $first = $c->getItem('counter');
        $second = $c->getItem('counter');
        $this->assertSame(1, $first);
        $this->assertSame($first, $second);
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
