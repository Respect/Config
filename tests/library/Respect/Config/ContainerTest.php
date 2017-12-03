<?php
namespace Respect\Config;

use Respect\Test\StreamWrapper;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!in_array($this->getName(), array('testLoadFile', 'testLoadFileMultiple')))
            return;

        $ini = <<<INI
foo = bar
baz = bat
INI;
        $pnd = <<<PND
happy = panda
panda = happy
PND;
        StreamWrapper::setStreamOverrides(array(
            'exists.ini' => $ini,
            'multiple/foo-bar-baz.ini' => $ini,
            'multiple/happy-panda.ini' => $pnd,
        ));
    }

    public function tearDown()
    {
        StreamWrapper::releaseOverrides();
    }

    public function testLoadArray()
    {
        $ini = <<<INI
foo = bar
baz = bat
INI;
        $c = new Container(parse_ini_string($ini, true));
        $this->assertTrue(isset($c->foo));
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    public function testLoadFile()
    {
        $c = new Container('exists.ini');
        $this->assertTrue(isset($c->foo));
        $this->assertEquals('bar', $c->getItem('foo'));
        $this->assertEquals('bat', $c->getItem('baz'));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid input. Must be a valid file or array
     */
    public function testLoadStringWithInvalidIni()
    {
        $c = new Container(1);
        $c->a;
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid configuration string
     */
    public function testLoadFileThatDoesNotExistsThrowException()
    {
        $c = new Container('inexistent.ini');
        $c->foo;
    }

    /**
     * @group issue
     * @ticket 30
     */
    public function testLateExpansionWhenItemDefinitionIsDonePassingAnArray()
    {
        $config = "
account = [user]

[development]
user = alganet

[production]
user = respect
";
        $config = parse_ini_string($config,true);
        $config = array_merge($config['production'], $config);
        $container = new Container($config);

        $this->assertEquals(
            'respect',
            $container->account
        );
    }
}

