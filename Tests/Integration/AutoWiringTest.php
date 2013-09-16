<?php

namespace HCO\AutoWiringBundle\Tests\Controller;

use HCO\AutoWiringBundle\DependencyInjection\Compiler\AutoWiringCompilerPass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class AutoWiringTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $containerBuilder;

    public function setUp()
    {
        $this->containerBuilder = new ContainerBuilder();
        $this->containerBuilder->setDefinition(
            'annotation_reader',
            new Definition(
                'Doctrine\Common\Annotations\AnnotationReader'
            )
        );
    }
    public function testSimple()
    {
        $this->containerBuilder->setDefinition(
            'dependency',
            new Definition(
                '\StdClass'
            )
        );

        $definition = new Definition(
            'HCO\AutoWiringBundle\Tests\Helpers\ServiceWithDependency'
        );
        $definition->addTag(
            'hco.autowire'
        );

        $this->containerBuilder->setDefinition(
            'depending_service',
            $definition

        );

        $this->compile();

        $this->assertSame(
            $this->containerBuilder->get('dependency'),
            $this->containerBuilder->get('depending_service')->stdObject
        );
    }

    /**
     * @expectedException HCO\AutoWiringBundle\Exception
     * @expectedExceptionMessage There's not exactly one unqualified service registered for stdClass, thus autowiring is impossible.
     */
    public function testSameClassWithoutQualifier()
    {
        $this->containerBuilder->setDefinition(
            'dependency',
            new Definition(
                '\StdClass'
            )
        );

        $this->containerBuilder->setDefinition(
            'dependency_2',
            new Definition(
                '\StdClass'
            )
        );

        $definition = new Definition(
            'HCO\AutoWiringBundle\Tests\Helpers\ServiceWithDependency'
        );


        $definition->addTag(
            'hco.autowire'
        );

        $this->containerBuilder->setDefinition(
            'depending_service',
            $definition

        );

        $this->compile();
    }

    /**
     * @expectedException HCO\AutoWiringBundle\Exception
     * @expectedExceptionMessage Class stdClass with qualifier master is not registered with the DependencyRegistry
     */
    public function testDependencyWithMissingQualifier()
    {
        $this->containerBuilder->setDefinition(
            'dependency',
            new Definition(
                '\StdClass'
            )
        );

        $this->containerBuilder->setDefinition(
            'dependency_2',
            new Definition(
                '\StdClass'
            )
        );

        $definition = new Definition(
            'HCO\AutoWiringBundle\Tests\Helpers\ServiceDependingOnQualifiedService'
        );


        $definition->addTag(
            'hco.autowire'
        );

        $this->containerBuilder->setDefinition(
            'depending_service',
            $definition

        );

        $this->compile();
    }


    public function testDependencyWithQualifier()
    {
        $this->containerBuilder->setDefinition(
            'dependency',
            new Definition(
                '\StdClass'
            )
        );

        $definition = new Definition(
            '\StdClass'
        );
        $definition->addTag(
            'hco.autowire.qualifier',
            array(
                'qualifier' => 'master'
            )
        );

        $this->containerBuilder->setDefinition(
            'dependency_2',
            $definition
        );

        $definition = new Definition(
            'HCO\AutoWiringBundle\Tests\Helpers\ServiceDependingOnQualifiedService'
        );


        $definition->addTag(
            'hco.autowire'
        );

        $this->containerBuilder->setDefinition(
            'depending_service',
            $definition

        );

        $this->compile();

        $this->containerBuilder->get('dependency_2')->foo = 'bar';

        $this->assertSame(
            'bar',
            $this->containerBuilder->get('depending_service')->dependency->foo
        );
    }

    private function compile()
    {
        $compilerPass = new AutoWiringCompilerPass();
        $this->containerBuilder->addCompilerPass($compilerPass);
        $this->containerBuilder->compile();
    }
}
