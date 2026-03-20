<?php

declare(strict_types=1);

namespace Respect\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

#[CoversClass(NotFoundException::class)]
final class NotFoundExceptionTest extends TestCase
{
    public function testImplementsPsr11Interface(): void
    {
        $e = new NotFoundException('not found');
        $this->assertInstanceOf(NotFoundExceptionInterface::class, $e);
        $this->assertInstanceOf(Throwable::class, $e);
        $this->assertEquals('not found', $e->getMessage());
    }
}
