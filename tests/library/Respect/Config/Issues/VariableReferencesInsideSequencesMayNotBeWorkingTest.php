<?php
namespace Respect\Config;

class VariableReferencesInsideSequencesMayNotBeWorkingTestTest extends \PHPUnit_Framework_TestCase
{

    /**
	 * @group 	issues
	 * @ticket  25	
	 */
	public function testConjecture25()
	{
		$config = "
bar = 'bar';
foo = 'bar';
conjecture = [[foo]] 
";
        $expected = 'bar';
        $config  = parse_ini_string($config,true);
        $container = new Container($config);
        $this->assertNotEquals($expected, $container->conjecture);
	}


}
