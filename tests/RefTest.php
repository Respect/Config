<?php

declare(strict_types=1);

namespace Respect\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Ref::class)]
final class RefTest extends TestCase
{
    public function testConstructorSetsId(): void
    {
        $ref = new Ref('some.container.key');
        $this->assertEquals('some.container.key', $ref->id);
    }

    public function testIdIsReadonly(): void
    {
        $ref = new Ref('my.key');
        $reflection = new ReflectionProperty($ref, 'id');
        $this->assertTrue($reflection->isReadOnly());
    }
}
