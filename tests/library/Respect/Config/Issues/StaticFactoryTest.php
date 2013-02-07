<?php
namespace Respect\Config;

class StaticFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group issues
     * @ticket 9
     */
    public function testInstance()
    {
        $i = new Instantiator(__NAMESPACE__.'\\StaticTest');
        $i->setParam('factory', array(array()));
        $this->assertAttributeNotEmpty('staticMethodCalls', $i);
        $this->assertInstanceOf('DateTime', $i->getInstance());
    }
}

class StaticTest
{
    private function __construct() {}
    public static function factory()
    {
        return new \DateTime();
    }
}

