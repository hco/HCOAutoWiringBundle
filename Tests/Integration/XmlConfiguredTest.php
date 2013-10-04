<?php

namespace HCO\AutoWiringBundle\Tests\Controller;

use HCO\AutoWiringBundle\DependencyInjection\Compiler\AutoWiringCompilerPass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class XmlConfiguredTest extends \PHPUnit_Framework_TestCase
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
        $loader = new XmlFileLoader($this->containerBuilder, new FileLocator(__DIR__ . '/XmlConfigurations'));
        $loader->load('simple.xml');

        $this->compile();

        $this->assertSame(
            $this->containerBuilder->get('bar'),
            $this->containerBuilder->get('foo')->stdObject
        );
    }

    public function testQualified()
    {
        $loader = new XmlFileLoader($this->containerBuilder, new FileLocator(__DIR__ . '/XmlConfigurations'));
        $loader->load('qualified.xml');

        $this->compile();

        $this->assertSame(
            $this->containerBuilder->get('baz'),
            $this->containerBuilder->get('foo')->dependency
        );
    }

    public function testPrimary()
    {
        $loader = new XmlFileLoader($this->containerBuilder, new FileLocator(__DIR__ . '/XmlConfigurations'));
        $loader->load('primary.xml');

        $this->compile();

        $this->assertSame(
            $this->containerBuilder->get('baz'),
            $this->containerBuilder->get('foo')->stdObject
        );
    }

    public function testQualifiedUsingXml()
    {
        $loader = new XmlFileLoader($this->containerBuilder, new FileLocator(__DIR__ . '/XmlConfigurations'));
        $loader->load('qualified_using_xml.xml');

        $this->compile();

        $this->assertSame(
            $this->containerBuilder->get('baz'),
            $this->containerBuilder->get('foo')->stdObject
        );
    }


    private function compile()
    {
        $compilerPass = new AutoWiringCompilerPass();
        $this->containerBuilder->addCompilerPass($compilerPass);
        $this->containerBuilder->compile();
    }
}
