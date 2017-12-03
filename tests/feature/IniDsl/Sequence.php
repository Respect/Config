<?php

namespace Test\Feature;

use Respect\Config\Container;

class Sequence extends \PHPUnit_Framework_TestCase
{
    public function testInlineSequenceDeclarationTranslatesToArrayUponUsage()
    {
        $c = new Container(<<<INI
            fibonacci = [1, 1, 2, 3, 5]
INI
        );

        $this->assertEquals(
            array(1, 1, 2, 3, 5),
            $c->fibonacci,
            'Sequences are arrays declared much like in PHP (after 5.4 introduction of short array syntax).'
        );
    }

    public function testSectionSequenceDeclarationTranslatesToArrayUponUsage()
    {
        $c = new Container(<<<INI
[fibonacci]
0 = 1
1 = 1
2 = 2
3 = 3
4 = 5
INI
        );

        $this->assertEquals(
            array(1, 1, 2, 3, 5),
            $c->fibonacci,
            'INI sections are also sequences, where the name is the index of the resulting array.'
        );
    }

    public function testSectionSequenceDeclarationUsingAssociativeArrayIndexes()
    {
        $c = new Container(<<<INI
[components]
Config = unstable
Rest = stable
Template = experimental
Foundation = deprecated
INI
        );

        $this->assertEquals(
            array(
                "Config" => "unstable",
                "Rest" => "stable",
                "Template" => "experimental",
                "Foundation" => "deprecated"
            ),
            $c->components,
            'Sequences declared as INI sections can also use associative indexes.'
        );
    }
}
