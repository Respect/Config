<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

use function parse_ini_string;

#[CoversClass(Autowire::class)]
final class AutowireTest extends TestCase
{
    public function testAutowireResolvesTypedParamFromContainer(): void
    {
        $container = new Container();
        $date = new DateTime('2024-01-15');
        $container['DateTime'] = $date;

        $autowire = new Autowire(AutowireConsumer::class);
        $autowire->setContainer($container);
        $instance = $autowire->getInstance();

        $this->assertInstanceOf(AutowireConsumer::class, $instance);
        $this->assertSame($date, $instance->date);
    }

    public function testExplicitParamTakesPrecedenceOverAutowired(): void
    {
        $container = new Container();
        $containerDate = new DateTime('2024-01-01');
        $explicitDate = new DateTime('2025-06-15');
        $container['DateTime'] = $containerDate;

        $autowire = new Autowire(AutowireConsumer::class);
        $autowire->setContainer($container);
        $autowire->setParam('date', $explicitDate);
        $instance = $autowire->getInstance();

        $this->assertSame($explicitDate, $instance->date);
    }

    public function testAutowireWithoutContainerFallsBackToInstantiator(): void
    {
        $autowire = new Autowire(stdClass::class);
        $autowire->setParam('foo', 'bar');
        $instance = $autowire->getInstance();

        $this->assertInstanceOf(stdClass::class, $instance);
        $this->assertEquals('bar', $instance->foo);
    }

    public function testBuiltinTypeHintsAreNotAutowired(): void
    {
        $container = new Container();
        $container['string'] = 'hello';

        $autowire = new Autowire(AutowireWithBuiltin::class);
        $autowire->setContainer($container);
        $autowire->setParam('name', 'world');
        $instance = $autowire->getInstance();

        $this->assertEquals('world', $instance->name);
    }

    public function testAutowireMultipleParams(): void
    {
        $container = new Container();
        $date = new DateTime('2024-01-15');
        $container['DateTime'] = $date;
        $container[AutowireDependency::class] = new AutowireDependency('injected');

        $autowire = new Autowire(AutowireMultiParam::class);
        $autowire->setContainer($container);
        $instance = $autowire->getInstance();

        $this->assertSame($date, $instance->date);
        $this->assertEquals('injected', $instance->dep->value);
    }

    public function testAutowireMixedExplicitAndResolved(): void
    {
        $container = new Container();
        $container[AutowireDependency::class] = new AutowireDependency('auto');

        $autowire = new Autowire(AutowireMultiParam::class);
        $autowire->setContainer($container);
        $autowire->setParam('date', new DateTime('2025-01-01'));
        $instance = $autowire->getInstance();

        $this->assertEquals('2025-01-01', $instance->date->format('Y-m-d'));
        $this->assertEquals('auto', $instance->dep->value);
    }

    public function testAutowireSkipsParamNotInContainer(): void
    {
        $container = new Container();
        // Container has DateTime but NOT AutowireDependency

        $date = new DateTime();
        $container['DateTime'] = $date;

        $autowire = new Autowire(AutowireOptionalDep::class);
        $autowire->setContainer($container);
        $instance = $autowire->getInstance();

        $this->assertSame($date, $instance->date);
        $this->assertNull($instance->dep);
    }

    public function testAutowireViaContainerIniSyntax(): void
    {
        $ini = <<<'INI'
[Respect\Config\AutowireDependency Respect\Config\AutowireDependency]
value = from_config

[consumer autowire Respect\Config\AutowireTypedConsumer]
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));

        $consumer = $c->getItem('consumer');
        $this->assertInstanceOf(AutowireTypedConsumer::class, $consumer);
        $this->assertInstanceOf(AutowireDependency::class, $consumer->dep);
        $this->assertEquals('from_config', $consumer->dep->value);
    }

    public function testAutowireViaContainerWithExplicitOverride(): void
    {
        $ini = <<<'INI'
[Respect\Config\AutowireDependency Respect\Config\AutowireDependency]
value = default

[dep2 Respect\Config\AutowireDependency]
value = explicit

[consumer autowire Respect\Config\AutowireTypedConsumer]
dep = [dep2]
INI;
        $c = new Container();
        (new IniLoader($c))->fromArray(self::parseIni($ini));

        $consumer = $c->getItem('consumer');
        $this->assertEquals('explicit', $consumer->dep->value);
    }

    public function testAutowireNestedInstantiatorAsParam(): void
    {
        $container = new Container();

        $inner = new Instantiator(AutowireDependency::class);
        $inner->setParam('value', 'lazy');

        $autowire = new Autowire(AutowireTypedConsumer::class);
        $autowire->setContainer($container);
        $autowire->setParam('dep', $inner);
        $instance = $autowire->getInstance();

        $this->assertEquals('lazy', $instance->dep->value);
    }

    public function testContainerSetsContainerOnAutowireViaOffsetSet(): void
    {
        $container = new Container();
        $dep = new AutowireDependency('hello');
        $container[AutowireDependency::class] = $dep;

        $autowire = new Autowire(AutowireTypedConsumer::class);
        $container['consumer'] = $autowire;

        $consumer = $container->getItem('consumer');
        $this->assertSame($dep, $consumer->dep);
    }

    public function testAutowireSingleton(): void
    {
        $container = new Container();
        $container['DateTime'] = new DateTime();

        $autowire = new Autowire(AutowireConsumer::class);
        $autowire->setContainer($container);

        $first = $autowire->getInstance();
        $second = $autowire->getInstance();
        $this->assertSame($first, $second);
    }

    public function testAutowireStripsAllNullParams(): void
    {
        $container = new Container();
        $autowire = new Autowire(AutowireAllOptional::class);
        $autowire->setContainer($container);
        $instance = $autowire->getInstance();
        $this->assertInstanceOf(AutowireAllOptional::class, $instance);
        $this->assertNull($instance->a);
        $this->assertNull($instance->b);
    }

    public function testExplicitNullIsNotOverriddenByAutowire(): void
    {
        $container = new Container();
        $container['DateTime'] = new DateTime();
        $container[AutowireDependency::class] = new AutowireDependency('from_container');

        $autowire = new Autowire(AutowireOptionalDep::class);
        $autowire->setContainer($container);
        $autowire->setParam('dep', null);

        $instance = $autowire->getInstance();
        $this->assertInstanceOf(DateTime::class, $instance->date);
        $this->assertNull($instance->dep, 'Explicit null must not be overridden by autowiring');
    }

    public function testAutowireWithoutContainerLazyLoadsParams(): void
    {
        $inner = new Instantiator(AutowireDependency::class);
        $inner->setParam('value', 'lazy_loaded');

        $autowire = new Autowire(AutowireTypedConsumer::class);
        // No setContainer call — falls back to lazyLoad path
        $autowire->setParam('dep', $inner);
        $instance = $autowire->getInstance();
        $this->assertEquals('lazy_loaded', $instance->dep->value);
    }

    /** @return array<string, mixed> */
    private static function parseIni(string $ini): array
    {
        $result = parse_ini_string($ini, true);
        self::assertIsArray($result);

        return $result;
    }
}

class AutowireConsumer
{
    public function __construct(public DateTime $date)
    {
    }
}

class AutowireWithBuiltin
{
    public function __construct(public string $name)
    {
    }
}

class AutowireDependency
{
    public function __construct(public string $value = 'default')
    {
    }
}

class AutowireMultiParam
{
    public function __construct(public DateTime $date, public AutowireDependency $dep)
    {
    }
}

class AutowireOptionalDep
{
    public function __construct(public DateTime $date, public AutowireDependency|null $dep = null)
    {
    }
}

class AutowireTypedConsumer
{
    public function __construct(public AutowireDependency $dep)
    {
    }
}

class AutowireAllOptional
{
    public function __construct(public DateTime|null $a = null, public DateTime|null $b = null)
    {
    }
}
