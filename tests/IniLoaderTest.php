<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;
use InvalidArgumentException;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

use function parse_ini_string;

use const E_USER_ERROR;

#[CoversClass(IniLoader::class)]
final class IniLoaderTest extends TestCase
{
    public function testFromArray(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni('foo = bar'));
        $this->assertEquals('bar', $container->getItem('foo'));
    }

    public function testFromString(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromString("foo = bar\nbaz = bat");
        $this->assertEquals('bar', $container->getItem('foo'));
        $this->assertEquals('bat', $container->getItem('baz'));
    }

    public function testFromStringInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid configuration string');
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromString('');
    }

    public function testFromFile(): void
    {
        $structure = ['test.ini' => "foo = bar\nbaz = bat"];
        vfsStream::setup('root', null, $structure);

        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromFile(vfsStream::url('root') . '/test.ini');
        $this->assertEquals('bar', $container->getItem('foo'));
        $this->assertEquals('bat', $container->getItem('baz'));
    }

    public function testFromFileInvalid(): void
    {
        $vfs = vfsStream::setup('bad');
        vfsStream::newFile('unreadable.ini', 0000)->at($vfs)->setContent('foo = bar');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid configuration INI file');
        $container = new Container();
        $loader = new IniLoader($container);
        @$loader->fromFile(vfsStream::url('bad') . '/unreadable.ini');
    }

    public function testInterpretWithArray(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->interpret(['foo' => 'bar']);
        $this->assertEquals('bar', $container->getItem('foo'));
    }

    public function testInterpretWithString(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->interpret('foo = bar' . "\n" . 'baz = bat');
        $this->assertEquals('bar', $container->getItem('foo'));
    }

    public function testInterpretWithFile(): void
    {
        $structure = ['test.ini' => 'foo = bar'];
        vfsStream::setup('root', null, $structure);

        $container = new Container();
        $loader = new IniLoader($container);
        $loader->interpret(vfsStream::url('root') . '/test.ini');
        $this->assertEquals('bar', $container->getItem('foo'));
    }

    public function testInterpretWithNull(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->interpret(null);
        $this->assertFalse($container->has('anything'));
    }

    public function testInterpretWithInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input. Must be a valid file or array');
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->interpret(1);
    }

    public function testExpandVars(): void
    {
        $ini = <<<'INI'
db_driver = "mysql"
db_host   = "localhost"
db_name   = "my_database"
db_dsn    = "[db_driver]:host=[db_host];dbname=[db_name]"
INI;
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni($ini));
        $this->assertEquals(
            'mysql:host=localhost;dbname=my_database',
            $container->getItem('db_dsn'),
        );
    }

    public function testInstantiator(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni('[foo \stdClass]'));
        $instantiator = $container->getItem('foo', true);
        $this->assertEquals('\stdClass', $instantiator->getClassName());
    }

    public function testConstants(): void
    {
        $ini = <<<'INI'
foo = E_USER_ERROR
INI;
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni($ini));
        $this->assertEquals(E_USER_ERROR, $container->getItem('foo'));
    }

    public function testFactoryModifier(): void
    {
        $ini = <<<'INI'
[now new DateTime]
datetime = now
INI;
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni($ini));
        $raw = $container->getItem('now', true);
        $this->assertInstanceOf(Factory::class, $raw);
    }

    public function testAutowireModifier(): void
    {
        $ini = <<<'INI'
[consumer autowire \stdClass]
INI;
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni($ini));
        $raw = $container->getItem('consumer', true);
        $this->assertInstanceOf(Autowire::class, $raw);
    }

    public function testInstanceofSyntax(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni('[instanceof DateTime]'));
        $this->assertInstanceOf(DateTime::class, $container->getItem('DateTime'));
    }

    public function testSequences(): void
    {
        $ini = <<<'INI'
greetings = [hello, world]
INI;
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni($ini));
        $this->assertEquals(['hello', 'world'], $container->getItem('greetings'));
    }

    public function testMultipleLoadsMergeState(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni('foo = bar'));
        $loader->fromArray(self::parseIni('baz = bat'));
        $this->assertEquals('bar', $container->getItem('foo'));
        $this->assertEquals('bat', $container->getItem('baz'));
    }

    public function testFromArrayPassesClosureThrough(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(['fn' => static fn() => 'result']);
        $this->assertEquals('result', $container->getItem('fn'));
    }

    public function testFromArrayPassesInstantiatorThrough(): void
    {
        $instantiator = new Instantiator('stdClass');
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(['obj' => $instantiator]);
        $raw = $container->getItem('obj', true);
        $this->assertSame($instantiator, $raw);
    }

    public function testSections(): void
    {
        $ini = <<<'INI'
[sec]
foo = bar
baz = bat
INI;
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni($ini));
        $section = $container->getItem('sec');
        $this->assertEquals('bar', $section['foo']);
        $this->assertEquals('bat', $section['baz']);
    }

    public function testInstantiatorWithSingleConstructorParam(): void
    {
        $ini = <<<'INI'
foo \stdClass = bar
INI;
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni($ini));
        $raw = $container->getItem('foo', true);
        $this->assertInstanceOf(Instantiator::class, $raw);
        $this->assertEquals('bar', $raw->getParam('__construct'));
    }

    public function testKeyHasStateInstanceReusesExistingEntry(): void
    {
        $container = new Container();
        $container['foo'] = 'existing';
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni('[foo \stdClass]'));
        $this->assertEquals('existing', $container->getItem('foo \stdClass'));
    }

    public function testParseValuePassesInstantiatorThrough(): void
    {
        $inner = new Instantiator('stdClass');
        $inner->setParam('x', 'y');
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(['outer stdClass' => ['child' => $inner]]);
        $result = $container->getItem('outer');
        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertInstanceOf(stdClass::class, $result->child);
    }

    public function testEmptyValueBecomesNull(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(['key' => '']);
        $this->assertNull($container['key']);
    }

    public function testNonStringValuePassedThrough(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(['num' => 42]);
        $this->assertSame(42, $container->getItem('num'));
    }

    public function testReferenceResolution(): void
    {
        $ini = <<<'INI'
greeting = hello
ref = [greeting]
INI;
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni($ini));
        $this->assertEquals('hello', $container->getItem('ref'));
    }

    public function testParseValueHandlesNestedArray(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(['outer stdClass' => ['items' => ['a', 'b']]]);
        $raw = $container->getItem('outer', true);
        $this->assertEquals(['a', 'b'], $raw->getParam('items'));
    }

    public function testLoadStaticFactory(): void
    {
        $container = IniLoader::load(self::parseIni('foo = bar'));
        $this->assertInstanceOf(Container::class, $container);
        $this->assertEquals('bar', $container->getItem('foo'));
    }

    public function testLoadStaticFactoryWithExistingContainer(): void
    {
        $container = new Container(['existing' => 'value']);
        $result = IniLoader::load(self::parseIni('foo = bar'), $container);
        $this->assertSame($container, $result);
        $this->assertEquals('value', $result->getItem('existing'));
        $this->assertEquals('bar', $result->getItem('foo'));
    }

    public function testFromArrayReturnsContainer(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $result = $loader->fromArray(self::parseIni('foo = bar'));
        $this->assertSame($container, $result);
    }

    public function testFromStringReturnsContainer(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $result = $loader->fromString("foo = bar\nbaz = bat");
        $this->assertSame($container, $result);
    }

    public function testFromFileReturnsContainer(): void
    {
        $structure = ['test.ini' => 'foo = bar'];
        vfsStream::setup('fluent', null, $structure);

        $container = new Container();
        $loader = new IniLoader($container);
        $result = $loader->fromFile(vfsStream::url('fluent') . '/test.ini');
        $this->assertSame($container, $result);
    }

    public function testInterpretReturnsContainer(): void
    {
        $container = new Container();
        $loader = new IniLoader($container);
        $result = $loader->interpret(['foo' => 'bar']);
        $this->assertSame($container, $result);
    }

    public function testClassConstantResolution(): void
    {
        $ini = <<<'INI'
foo = \Respect\Config\IniLoaderTestConstant::VALUE
INI;
        $container = new Container();
        $loader = new IniLoader($container);
        $loader->fromArray(self::parseIni($ini));
        $this->assertEquals(IniLoaderTestConstant::VALUE, $container->getItem('foo'));
    }

    public function testNumericCoercionInteger(): void
    {
        $c = IniLoader::load(self::parseIni('val = 42'));
        $this->assertSame(42, $c->getItem('val'));
    }

    public function testNumericCoercionZero(): void
    {
        $c = IniLoader::load(self::parseIni('val = 0'));
        $this->assertSame(0, $c->getItem('val'));
    }

    public function testNumericCoercionFloat(): void
    {
        $c = IniLoader::load(self::parseIni('val = 3.14'));
        $this->assertSame(3.14, $c->getItem('val'));
    }

    public function testNumericCoercionScientific(): void
    {
        $c = IniLoader::load(self::parseIni('val = 1e3'));
        $this->assertSame(1000.0, $c->getItem('val'));
    }

    public function testNumericCoercionNegative(): void
    {
        $ini = <<<'INI'
[obj DateTime]
setTimestamp[] = -1
INI;
        $c = IniLoader::load(self::parseIni($ini));
        $this->assertSame(-1, $c->getItem('obj')->getTimestamp());
    }

    /** @return array<string, mixed> */
    private static function parseIni(string $ini): array
    {
        $result = parse_ini_string($ini, true);
        self::assertIsArray($result);

        return $result;
    }
}

class IniLoaderTestConstant
{
    public const string VALUE = 'XPTO';
}
