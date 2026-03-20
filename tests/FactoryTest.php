<?php

declare(strict_types=1);

namespace Respect\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Factory::class)]
final class FactoryTest extends TestCase
{
    public function testAlwaysCreatesNewInstance(): void
    {
        $factory = new Factory(stdClass::class);
        $factory->setParam('foo', 'bar');

        $first = $factory->getInstance();
        $second = $factory->getInstance();

        $this->assertInstanceOf(stdClass::class, $first);
        $this->assertInstanceOf(stdClass::class, $second);
        $this->assertNotSame($first, $second);
    }

    public function testForceNewIsIgnoredAlwaysNew(): void
    {
        $factory = new Factory(stdClass::class);

        $first = $factory->getInstance(false);
        $second = $factory->getInstance(false);

        $this->assertNotSame($first, $second);
    }

    public function testFactoryWithConstructorParams(): void
    {
        $factory = new Factory('DateTime');
        $factory->setParam('datetime', '2024-06-15');

        $first = $factory->getInstance();
        $second = $factory->getInstance();

        $this->assertEquals('2024-06-15', $first->format('Y-m-d'));
        $this->assertEquals('2024-06-15', $second->format('Y-m-d'));
        $this->assertNotSame($first, $second);
    }
}
