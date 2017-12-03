<?php

namespace Test\Feature;

use Respect\Config\Container;
use Respect\Test\StreamWrapper;

class Psr11Test extends \PHPUnit_Framework_TestCase
{
    /**
     * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-11-container.md
     * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-11-container-meta.md
     */
    public function testContainerInteropMethodsAkaPsr11()
    {
        $ini = <<<INI
foo = bar
baz = bat
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));

        $this->assertTrue(
            $c->has('foo'),
            'Searching an existing item must succeed.'
        );
        $this->assertEquals(
            'bar',
            $c->get('foo'),
            'Retrieving an existing item must succeed.'
        );
        $this->assertEquals(
            'bat',
            $c->get('baz'),
            'Retrieving an existing item must succeed.'
        );
    }

    /**
     * @expectedException Psr\Container\NotFoundExceptionInterface
     * @expectedExceptionMessage Item baz not found
     */
    public function testItemNotFoundInContainerMatchesInteropException()
    {
        $this->loadInvalidItem();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Item baz not found
     */
    public function testItemNotFoundInContainerMatchesPreviousRespectConfigException()
    {
        $this->loadInvalidItem();
    }

    /**
     * @expectedException Respect\Config\InvalidArgumentException
     * @expectedExceptionMessage Item baz not found
     */
    public function testItemNotFoundInContainerMatchesCustomRespectException()
    {
        $this->loadInvalidItem();
    }

    private function loadInvalidItem()
    {
        $ini = <<<INI
foo = bar
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $c->get('baz');
    }

    /**
     * @expectedException Psr\Container\ContainerExceptionInterface
     */
    public function testContainerExceptionMatchesInteropException()
    {
        $this->parseInvalidIni();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testContainerExceptionMatchesPreviousRespectConfigException()
    {
        $this->parseInvalidIni();
    }

    /**
     * @expectedException Respect\Config\InvalidArgumentException
     */
    public function testContainerExceptionUsesCustomRespectException()
    {
        $this->parseInvalidIni();
    }

    private function parseInvalidIni()
    {
        $c = new Container(14);
        $c->foo;
    }
}

