<?php
namespace Respect\Config;

class InstatiatorImplementsInvoke extends \PHPUnit_Framework_TestCase
{
    /**
     * @group   issues
     * @ticket  24
     */
    public function testLazyLoadedParameters()
    {
        $config = "
my_string = 'Hey you!'

[hello Respect\Config\MyLazyLoadedHelloWorld]
string = [my_string]
";
        $expected  = 'Hello World!';
        $container = new Container($config);
        $container->my_string = $expected;
        $this->assertEquals($expected, (string) $container->hello);
    }

    public function testLazyLoadedInstance()
    {
        $config = "
my_string = 'Hey you!'

[hello Respect\Config\MyLazyLoadedHelloWorld]
string = [my_string]

[consumer Respect\Config\MyLazyLoadedHelloWorldConsumer]
hello = [hello]
    ";
        $expected  = 'Hello World!';
        $container = new Container($config);
        $container->my_string = $expected;
        $this->assertEquals($expected, (string) $container->hello);
        $container = new Container($config);
        $container->{"hello Respect\\Config\\MyLazyLoadedHelloWorld"} = array('string' => $expected);
        $this->assertEquals($expected, (string) $container->hello);
        $container = new Container($config);
        $container->hello = new MyLazyLoadedHelloWorld($expected);
        $this->assertEquals($expected, (string) $container->hello);
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

