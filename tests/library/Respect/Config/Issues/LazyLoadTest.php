<?php
namespace Respect\Config;

class LazyLoadTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @group 	issues
	 * @ticket 	14
	 */
	public function testLazyLoadedParameters()
	{
		$this->markTestSkipped('No solution found yet');
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