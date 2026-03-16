<?php

declare(strict_types=1);

namespace Respect\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_merge;
use function parse_ini_string;

#[CoversClass(Container::class)]
#[Group('issues')]
final class EnviromentConfigurationTest extends TestCase
{
    public function testEnviromentConfiguration30(): void
    {
        $config = '
[development]
user = alganet

[production]
user = respect
account = [user]
';
        $expected = 'respect';
        $environment = 'production';
        $parsed = parse_ini_string($config, true);
        $this->assertIsArray($parsed);
        $config = array_merge($parsed[$environment], $parsed);
        $container = new Container($config);
        $this->assertEquals($expected, $container->getItem('account'));
    }
}
