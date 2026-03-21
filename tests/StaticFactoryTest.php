<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

#[CoversClass(Instantiator::class)]
#[Group('issues')]
final class StaticFactoryTest extends TestCase
{
    public function testInstance(): void
    {
        $i = new Instantiator(__NAMESPACE__ . '\\StaticFactoryStub');
        $i->setParam('factory', [[]]);
        $ref = new ReflectionObject($i);
        $prop = $ref->getProperty('staticMethodCalls');
        $this->assertNotEmpty($prop->getValue($i));
        $this->assertInstanceOf(DateTime::class, $i->getInstance());
    }
}
