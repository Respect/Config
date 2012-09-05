<?php
namespace Respect\Config;

class VariableReferencesInsideSequencesMayNotBeWorkingTestTest extends \PHPUnit_Framework_TestCase
{
    private $config;
    public function setUp()
    {
		$this->config = "
bar = 'bar';
foo = 'bar';
conjecture = [[foo]] 
";
    }


    /**
	 * @group 	issues
	 * @ticket  25	
	 */
	public function testConjecture25()
	{
        $expected = 'bar';
        $config  = parse_ini_string($this->config,true);
        $container = new Container($config);
        $this->assertNotEquals($expected, $container->conjecture);
	}

    public function testFixNestedVariable()
    {
        $expected = 'bar';
        $config  = parse_ini_string($this->config,true);
        $container = new Container($config);
        $this->assertEquals($expected, $container->conjecture);
    }

}
