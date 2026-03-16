<?php

declare(strict_types=1);

namespace Respect\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Container::class)]
#[Group('issues')]
final class LazyLoadTest extends TestCase
{
    public function testLazyLoadedParameters(): void
    {
        $config = "
my_string = 'Hey you!'

[hello Respect\Config\MyLazyLoadedHelloWorld]
string = [my_string]
";
        $expected = 'Hello World!';
        $container = new Container($config);
        $container['my_string'] = $expected;
        $this->assertEquals($expected, (string) $container->getItem('hello'));
    }

    public function testLazyLoadedInstance(): void
    {
        $config = "
my_string = 'Hey you!'

[hello Respect\Config\MyLazyLoadedHelloWorld]
string = [my_string]

[consumer Respect\Config\MyLazyLoadedHelloWorldConsumer]
hello = [hello]
    ";
        $expected = 'Hello World!';
        $container = new Container($config);
        $container['my_string'] = $expected;
        $this->assertEquals($expected, (string) $container->getItem('hello'));
        $container = new Container($config);
        $container->{'hello Respect\\Config\\MyLazyLoadedHelloWorld'} = ['string' => $expected];
        $this->assertEquals($expected, (string) $container->getItem('hello'));
        $container = new Container($config);
        // __set detects existing Instantiator at 'hello' and calls setInstance()
        $container->hello = new MyLazyLoadedHelloWorld($expected);
        $this->assertEquals($expected, (string) $container->getItem('hello'));
    }
}

class MyLazyLoadedHelloWorldConsumer
{
    protected string $string;

    public function __construct(mixed $hello)
    {
        $this->string = $hello;
    }

    public function __toString(): string
    {
        return $this->string;
    }
}

class MyLazyLoadedHelloWorld
{
    public function __construct(protected string $string)
    {
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
