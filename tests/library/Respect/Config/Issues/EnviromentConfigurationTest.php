<?php
namespace Respect\Config;

class EnviromentConfigurationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group   issues
     * @ticket  30
     */
    public function testEnviromentConfiguration30()
    {
        $config = "
[development]
user = alganet

[production]
user = respect
account = [user]
";
        $expected = 'respect';
        $ENVIRONMENT = 'production';
        $config  = parse_ini_string($config,true);
        $config  = array_merge($config[$ENVIRONMENT], $config);
        $container = new Container($config);
        $this->assertEquals($expected, $container->account); //respect
    }
}

