<?php

namespace Test\Feature;

use Respect\Config\Container;

class Instantiation extends \PHPUnit_Framework_TestCase
{
    public function testCreatingAnInstanceOfStdclassUsingSectionDeclarationOfAnIni()
    {
        $c = new Container(<<<INI
[foo stdClass]
INI
        );

        $this->assertInstanceOf(
            'StdClass',
            $c->foo,
            'INI sections with space in their names create new instances: ' . PHP_EOL .
            '  * The string before the space is the instance name' . PHP_EOL .
            '  * The string after the space is the instance type'
        );
    }

    public function testInstanceNameWithUnderline()
    {
        $c = new Container(<<<INI
[foo_bar stdClass]
INI
        );

        $this->assertInstanceOf(
            'StdClass',
            $c->foo_bar,
            'Instances with underline in their names should work as expected.'
        );
    }

    public function testCreatingAnInstanceOfStdclassWithinAValueName()
    {
        $c = new Container(<<<INI
foo stdClass =
INI
        );

        $this->assertInstanceOf(
            'StdClass',
            $c->foo,
            'INI value names can also trigger class instantiation, we prefer the section-way though.'
        );
    }

    public function testInstancePropertyDefinition()
    {
        $c = new Container(<<<INI
[person stdClass]
name = John Snow
mother = Lyanna Stark
father = Rhaegar Targaryen
INI
        );

        $this->assertInstanceOf(
            'StdClass',
            $c->person,
            'Every item in the section is treated as a public property and, therefore, defined in the instance.'
        );
        $this->assertEquals(
            'Rhaegar Targaryen',
            $c->person->father,
            'Did you guess that in the show?'
        );
        $this->assertEquals(
            'John Snow',
            $c->person->name,
            'Most boring character. Ever.'
        );
        $this->assertEquals(
            'Lyanna Stark',
            $c->person->mother,
            'Feel like talking about *that* butterfly effect?'
        );
    }

    public function testInstancePropertyDefinitionWithExpansionOfAnotherInstance()
    {
        $c = new Container(<<<INI
[foo_bar stdClass]

[bar_foo Test\Stub\WheneverWithAProperty]
test = [foo_bar]
INI
        );

        $this->assertEquals(
            get_class($c->foo_bar),
            get_class($c->bar_foo->test)
        );
    }


    public function testInstancePropertyOfTypeArray()
    {
        $ini = <<<INI
[foo stdClass]
foo[abc] = bar
foo[def] = bat
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $instantiator = $c->getItem('foo', true);
        $expected = array(
            'abc' => 'bar',
            'def' => 'bat'
        );
        $this->assertEquals($expected, $instantiator->getParam('foo'));
    }

    public function testPropertyDefinitionWithSequences()
    {
        $ini = <<<INI
[foo stdClass]
foo[abc] = [bat, blz]
foo[def] = bat
baz = [bat, blz]
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $instantiator = $c->getItem('foo', true);
        $expectedFoo = array(
            'abc' => array('bat', 'blz'),
            'def' => 'bat'
        );
        $expectedBaz = array('bat', 'blz');
        $this->assertEquals($expectedFoo, $instantiator->getParam('foo'));
        $this->assertEquals($expectedBaz, $instantiator->getParam('baz'));
    }

    public function testPropertyDefinitionWithVariableExpansion()
    {
        $ini = <<<INI
hi = someName
[foo stdClass]
foo[abc] = [bat, blz]
foo[def] = bat
baz = [bat, [hi]]
barr = [bat, [hi]]
INI;
        $c = new Container;
        $c->loadArray(parse_ini_string($ini, true));
        $instantiator = $c->getItem('foo', true);
        $expectedFoo = array(
            'abc' => array('bat', 'blz'),
            'def' => 'bat'
        );
        $expectedBaz = array('bat', 'someName');
        $this->assertEquals($expectedFoo, $instantiator->getParam('foo'));
        $this->assertEquals($expectedBaz, $instantiator->getParam('baz'));
    }

    public function testUsingTheSameInstanceTwiceReusesThePreviouslyCreatedInstances()
    {
        $c = new Container(<<<INI
[now DateTime]
INI
        );

        $firstValue = $c->now;
        $this->assertInstanceOf(
            'DateTime',
            $firstValue,
            'Instance type given should be respected.'
        );
        $this->assertSame(
            $firstValue,
            $c->now,
            'After instantiation, the instance remains the same. No matter how many times you use it.'
        );
    }

    public function testNewInstanceWithConstructorArgument()
    {
        $c = new Container(<<<INI
[home DateTimeZone]
timezone = "America/Sao_Paulo"
INI
        );

        $this->assertInstanceOf(
            'DateTimeZone',
            $c->home,
            'Constructor parameters can be defined as a sequence (array) passed as value to the "__construct".'
        );
        $this->assertEquals(
            'America/Sao_Paulo',
            $c->home->getName(),
            'Instances work like in any other place, you can call methods on them later...'
        );
    }

    /**
     * @group issue
     * @ticket 40
     */
    public function testInstanceExpansionWithTypeConstructorParameter()
    {
        $c = new Container(<<<INI
[now DateTime]

[typed Test\Stub\TypeHintWowMuchType]
date = [now];
INI
        );

        $this->assertInstanceOf(
            'Test\Stub\TypeHintWowMuchType',
            $c->typed,
            'That object requires a DateTime as constructor argument.'
        );
    }

    /**
     * @group issue
     * @ticket 9
     */
    public function testInstanceThroughStaticFactoryAlwaysCreateNewObjects()
    {
        $c = new Container(<<<INI
[y2k DateTime]
createFromFormat[] = [Y-m-d H:i:s, 2000-01-01 00:00:01]
INI
        );

        $firstCall = $c->y2k;
        $secondCall = $c->y2k;
        $this->assertInstanceOf(
            'DateTime',
            $firstCall,
            'Static factory behavior is to create an instance after calling a method.' . PHP_EOL .
            'When you define an instance as INI section and pass it a method name (which is always' . PHP_EOL .
            'followed by brackets), we will call "DateTime::createFromFormat" to retrieve the instance.'
        );
        $this->assertSame(
            $firstCall,
            $secondCall,
            'Even through we are creating instances from a method call, after the first instantiation the' . PHP_EOL .
            'instance is not changed. No matter how many times you use it.'
        );
    }

    public function testInstatiationOfNewObjectEveryTimeItIsUsed()
    {
        $c = new Container(<<<INI
[now new DateTime]
INI
        );

        $this->assertInstanceOf(
            'DateTime',
            $c->now,
            'Instance type should always be respected.'
        );
        $this->assertNotSame(
            $c->now,
            $c->now,
            'When "new" is used before declaring the type of instance, we will always create new instances.'
        );
    }

    public function testCallingMethodWithValueOnAnInstanceMultipleTimes()
    {
        $c = new Container(<<<INI
[day SplQueue]
enqueue[] = one
enqueue[] = two
INI
        );

        $this->assertEquals(
            'one',
            $c->day->dequeue(),
            'Queue was created and two items were enqueued.'
        );
        $this->assertEquals(
            'two',
            $c->day->dequeue(),
            'Methods are called in the order they are defined inside the section.'
        );
    }

    public function testCallingMethodWithoutValue()
    {
        $c = new Container(<<<INI
[db PDO]
dsn = sqlite::memory:
beginTransaction[] =
query[] = "CREATE TABLE foo(id INT)"
commit[] =
INI
        );

        $this->assertNotEmpty(
            $c->db->query('SELECT * FROM sqlite_master')->fetch()
        );
    }

    public function testInstanceOfDefinesTheInstanceNameAsItsOwnInstance()
    {
        $c = new Container(<<<INI
[instanceof DateTime]

[person StdClass]
name = Jeff
birthday = [DateTime]
INI
        );

        $this->assertInstanceOf(
            'DateTime',
            $c->DateTime,
            'Although the "DateTime" item is not defined, the `instanceof` operator registers it.'
        );
        $this->assertSame(
            $c->DateTime,
            $c->person->birthday,
            'You can use this kind of thing when you full class name is a nice one, and a single instance is relavant.'
        );
    }
}

namespace Test\Stub;

class WheneverWithAProperty
{
    public $test;
}

class TypeHintWowMuchType
{
    public $d;

    public function __construct(\DateTime $date)
    {
        $this->d = $date;
    }
}

