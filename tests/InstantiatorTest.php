<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

use function date_default_timezone_set;
use function func_num_args;
use function get_class;

#[CoversClass(Instantiator::class)]
final class InstantiatorTest extends TestCase
{
    public function testStaticMethodConstructor(): void
    {
        $i = new Instantiator('DateTime');
        $i->setParam('createFromFormat', [['Y-m-d', '2005-10-12']]);
        $s = $i->getInstance();
        $this->assertEquals('2005-10-12', $s->format('Y-m-d'));
    }

    public function testConstructorParamNames(): void
    {
        date_default_timezone_set('UTC');
        $i = new Instantiator('DateTime');
        $i->setParam('datetime', 'now');
        $i->setParam('timezone', $tz = new DateTimeZone('UTC'));
        $s = $i->getInstance();
        $this->assertEquals('DateTime', $s::class);
        $this->assertEquals($tz, $s->getTimezone());
    }

    public function testConstructorFull(): void
    {
        $i = new Instantiator('DateTime');
        $i->setParam(
            '__construct',
            ['now', $tz = new DateTimeZone('America/Sao_Paulo')],
        );
        $s = $i->getInstance();
        $this->assertEquals('DateTime', $s::class);
        $this->assertEquals($tz, $s->getTimezone());
    }

    public function testMethodNoParams(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\TestClass');
        $i->setParam('noParams', [
            [],
        ]);
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
    }

    public function testMethodWithObjectProperty(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\TestClass');
        $i->setParam('myProperty', 'bar');
        $i->setParam('usingProperty', [
            [],
        ]);
        $testObject = $i->getInstance();
        $this->assertTrue($testObject->myPropertyUsed);
    }

    public function testMethodSingleParam(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\TestClass');
        $i->setParam('oneParam', [
            [true],
        ]);
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
    }

    public function testMethodMultiParams(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\TestClass');
        $i->setParam('twoParams', [
            [true, true],
        ]);
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
    }

    public function testConstructorNullParams(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\TestClass');
        $i->setParam('__construct', [true]);
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
    }

    public function testConstructorNullParamsFalse(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\TestClass');
        $i->setParam('__construct', [false]);
        $s = $i->getInstance();
        $this->assertFalse($s->ok);
    }

    public function testProperties(): void
    {
        $i = new Instantiator('stdClass');
        $i->setParam('foo', 'bar');
        $i->setParam('baz', 'bat');
        $s = $i->getInstance();
        $this->assertEquals('bar', $s->foo);
        $this->assertEquals('bat', $s->baz);
    }

    public function testNestedInstantiators(): void
    {
        $i1 = new Instantiator('stdClass');
        $i2 = new Instantiator('stdClass');
        $i1->setParam('foo', $i2);
        $s = $i1->getInstance();
        $this->assertEquals('stdClass', get_class($s->foo));
    }

    public function testMagickInvoke(): void
    {
        $i1 = new Instantiator('stdClass');
        $i2 = new Instantiator('stdClass');
        $i1->setParam('foo', $i2);
        $s = $i1();
        $this->assertEquals('stdClass', get_class($s->foo));
    }

    public function testGetClassName(): void
    {
        $i = new Instantiator('DateTime');
        $this->assertEquals('DateTime', $i->getClassName());
    }

    public function testGetParam(): void
    {
        $i = new Instantiator('stdClass');
        $i->setParam('foo', 'bar');
        $this->assertEquals('bar', $i->getParam('foo'));
    }

    public function testGetParams(): void
    {
        $i = new Instantiator('stdClass');
        $i->setParam('foo', 'bar');
        $i->setParam('baz', 'bat');
        $this->assertEquals(['foo' => 'bar', 'baz' => 'bat'], $i->getParams());
    }

    public function testSetInstance(): void
    {
        $i = new Instantiator('stdClass');
        $obj = new stdClass();
        $obj->custom = true;
        $i->setInstance($obj);
        $this->assertSame($obj, $i->getInstance());
    }

    public function testConstructorWithInitialParams(): void
    {
        $i = new Instantiator('stdClass', ['foo' => 'bar', 'baz' => 'bat']);
        $s = $i->getInstance();
        $this->assertEquals('bar', $s->foo);
        $this->assertEquals('bat', $s->baz);
    }

    public function testTrailingNullParamsAreStripped(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\TestClass');
        $i->setParam('foo', true);
        $i->setParam('bar', null);
        $i->setParam('baz', null);
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
        $this->assertNull($s->bar);
        $this->assertNull($s->baz);
    }

    public function testMethodCallWithSingleNonArrayArg(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\TestClass');
        $i->setParam('oneParam', [true]);
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
    }

    public function testMethodCallWithNullArg(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\TestClass');
        $i->setParam('noParams', [null]);
        $s = $i->getInstance();
        $this->assertTrue($s->ok);
    }

    public function testStaticMethodReturningNonObject(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\StaticNonObjectReturn');
        $i->setParam('init', [[]]);
        $s = $i->getInstance();
        $this->assertInstanceOf(StaticNonObjectReturn::class, $s);
        $this->assertTrue($s->ready);
    }
}

class StaticNonObjectReturn
{
    public bool $ready = true;

    public static function init(): string
    {
        return 'not_an_object';
    }
}

class TestClass
{
    public bool $ok = false;

    public bool $myPropertyUsed = false;

    public string $myProperty = 'foo';

    public function __construct(mixed $foo = null, public mixed $bar = null, public mixed $baz = null)
    {
        if (!$foo) {
            return;
        }

        $this->ok = true;
    }

    public function usingProperty(): void
    {
        if ($this->myProperty !== 'bar') {
            return;
        }

        $this->myPropertyUsed = true;
    }

    public function noParams(): void
    {
        if (func_num_args() !== 0) {
            return;
        }

        $this->ok = true;
    }

    public function oneParam(mixed $ok): void
    {
        if (!$ok) {
            return;
        }

        $this->ok = true;
    }

    public function twoParams(mixed $ok, mixed $ok2): void
    {
        if (!$ok || !$ok2) {
            return;
        }

        $this->ok = true;
    }
}
