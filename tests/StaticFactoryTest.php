<?php
namespace Respect\Config;

class StaticFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @group issues
     * @ticket 9
     */
    public function testInstance()
    {
        $i = new Instantiator(__NAMESPACE__.'\\StaticTest');
        $i->setParam('factory', array(array()));
        $ref = new \ReflectionObject($i);
        $prop = $ref->getProperty('staticMethodCalls');
        $this->assertNotEmpty($prop->getValue($i));
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

