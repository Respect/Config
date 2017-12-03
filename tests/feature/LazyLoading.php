<?php

namespace Test\Feature;

use Respect\Config\Container;

class LazyLoading extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $GLOBALS['_SHIT_'] = false;
    }

    public function testLateExpansionOfItem()
    {
        $c = new Container(<<<INI
john = [me]
INI
        );

        $c->me = 'John Snow';

        $this->assertEquals(
            $c->me,
            $c->john,
            'Although the [me] variable didn\'t exist upon container creation, as everything is evaluated through lazy loading.'
        );
    }

    /**
     * @TODO We should lazy load just what we need. This test should work
     *       without throwing an Exception.
     * @expectedException Respect\Config\NotFoundException
     * @expectedExceptionMessage Item me not found
     */
    public function testLateExpansionOfJustWhatWeNeed()
    {
        $c = new Container(<<<INI
john = [me]
jane = Jane Doe
INI
        );

        $this->assertEquals(
            'Jane Doe',
            $c->jane,
            'We lazy load just the items we need.'
        );
    }

    public function testLazyLoadinessOnMultipleConfigLevels()
    {
        $c = new Container(<<<INI
[foo Test\Stub\WheneverIBornIPopulateAGlobalCalled_SHIT_]
child = ""

[bar Test\Stub\WheneverIBornIPopulateAGlobalCalled_SHIT_]
child = [foo]

[baz Test\Stub\WheneverIBornIPopulateAGlobalCalled_SHIT_]
child = [bar]
INI
        );

        $this->assertFalse(
            $GLOBALS['_SHIT_'],
            'If no instatiation happened, this global variable should be `false`.'
        );
        $this->assertInstanceOf(
            'Test\Stub\WheneverIBornIPopulateAGlobalCalled_SHIT_',
            $c->foo,
            'Using something to force instatiation.'
        );
        $this->assertTrue(
            $GLOBALS['_SHIT_'],
            'Believe me now?'
        );
    }

    public function testSequencesConstructingLazy()
    {
        $c = new Container(<<<INI
[bar Test\Stub\Bar]

[foo Test\Stub\Foo]
hello[] = ["opa", [bar]]
INI
        );

        $this->assertInstanceOf(
            'Test\Stub\Bar',
            $c->foo->bar
        );
    }

    /**
     * @group issue
     * @ticket 14
     * @ticket 24
     */
    public function testOverwriteOfItemBeforeExpansionOnAnother()
    {
        $c = new Container(<<<INI
my_string = "You won't see me"

[hello Test\Stub\MyLazyLoadedHelloWorld]
string = [my_string]
INI
        );

        $c->my_string = 'Hello World!';

        $this->assertEquals(
            'Hello World!',
            (string) $c->hello
        );
    }

    /**
     * @group issue
     * @ticket 26
     */
    public function testLazyLoadedInstance()
    {
        $config = "
my_string = 'Hey you!'

[hello Test\Stub\MyLazyLoadedHelloWorld]
string = [my_string]

[consumer Test\Stub\MyLazyLoadedHelloWorldConsumer]
hello = [hello]
    ";
        $expected  = 'Hello World!';
        $container = new Container($config);
        $container->my_string = $expected;
        $this->assertEquals($expected, (string) $container->hello);
        $container = new Container($config);
        $container->{"hello Test\\Stub\\MyLazyLoadedHelloWorld"} = array('string' => $expected);
        $this->assertEquals($expected, (string) $container->hello);
        $container = new Container($config);
        $container->hello = new \Test\Stub\MyLazyLoadedHelloWorld($expected);
        $this->assertEquals($expected, (string) $container->hello);
    }
}

namespace Test\Stub;

class WheneverIBornIPopulateAGlobalCalled_SHIT_
{
    function __construct()
    {
        $GLOBALS['_SHIT_'] = true;
    }
}

class Bar {}

class Foo
{
    function hey(\DateTime $date)
    {
       return $date;
    }

    function hello($some, Bar $bar)
    {
        $this->bar = $bar;
    }
}

class MyLazyLoadedHelloWorldConsumer
{
    protected $string;

    public function __construct($hello)
    {
        $this->string = $hello;
    }

    public function __toString()
    {
        return $this->string;
    }
}

class MyLazyLoadedHelloWorld
{
    protected $string;

    public function __construct($string)
    {
        $this->string = $string;
    }

    public function __toString()
    {
        return $this->string;
    }
}
