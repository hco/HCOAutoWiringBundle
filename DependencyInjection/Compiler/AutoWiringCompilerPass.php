<?php

namespace HCO\AutoWiringBundle\DependencyInjection\Compiler;

use Doctrine\Common\Annotations\AnnotationReader;
use HCO\AutoWiringBundle\Annotation\Qualifier;
use HCO\AutoWiringBundle\Annotation\RequireQualifier;
use HCO\AutoWiringBundle\DependencyRegistry;
use Sabre\VObject\Component\VAlarm;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The actual AutoWiring
 *
 * This class actually implements the AutoWiring.
 * It leverages the DependencyRegistry to create a map of classes which are
 * provided by every service.
 * It also reads the hco.autowire.qualifier and hco.autowire.primary tags from
 * the DIC.
 *
 * In order to find the classes provided by a service, it walks up the
 * inheritance chain and looks for all implemented interfaces in a service.
 * Every class in the inheritance chain will then be added to the DependencyRegistry.
 */
class AutoWiringCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        /** @var AnnotationReader $annotationReader */
        $annotationReader = $container->get('annotation_reader');

        $taggedServices = $container->findTaggedServiceIds(
            'hco.autowire'
        );
        $dependencyRegistry = $this->buildDependencyRegistry($container);

        foreach ($taggedServices as $serviceId => $tagAttributes) {
            $definition = $container->getDefinition($serviceId);
            $class      = new \ReflectionClass($definition->getClass());
            $container->addResource(new FileResource($class->getFileName()));

            $newArguments = array();

            $qualifiers = $this->getQualifiersForParameters($annotationReader, $class);

            foreach ($class->getConstructor()->getParameters() as $parameter) {
                if (isset($qualifiers[$parameter->getName()])) {
                    $newArguments[] = new Reference(
                        $dependencyRegistry->findQualified(
                            $parameter->getClass()->getName(),
                            $qualifiers[$parameter->getName()]
                        )
                    );
                } else {
                    $newArguments[] = new Reference(
                        $dependencyRegistry->findUnqualified($parameter->getClass()->getName())
                    );
                }
            }

            $definition->setArguments($newArguments);
        }
    }

    private function getProvidedClasses(\ReflectionClass $class, $classes = array())
    {
        $classes[] = $class->getName();
        $classes   = array_merge($classes, $class->getInterfaceNames());

        if ($class->getParentClass()) {
            $classes = array_merge($this->getProvidedClasses($class->getParentClass(), $classes));
        }

        return array_unique($classes);


    }

    /**
     * @param $annotationReader
     * @param $class
     */
    private function getQualifiersForParameters(AnnotationReader $annotationReader, \ReflectionClass $class)
    {
        /** @var RequireQualifier[] $annotations */
        $annotations = array_filter(
            $annotationReader->getMethodAnnotations(
                $class->getConstructor()
            ),
            function ($annotation) {
                return $annotation instanceof \HCO\AutoWiringBundle\Annotation\RequireQualifier;
            }
        );


        $parameterNameToQualifierMap = array();

        foreach ($annotations as $annotation) {
            $parameterNameToQualifierMap[$annotation->param] = $annotation->qualifier;
        }

        return $parameterNameToQualifierMap;
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return DependencyRegistry
     */
    private function buildDependencyRegistry(ContainerBuilder $container)
    {
        $dependencyRegistry = new DependencyRegistry();
        foreach ($container->getDefinitions() as $serviceId => $definition) {
            $className = $container->getParameterBag()->resolveValue(
                $definition->getClass()
            );

            if ($className === null) {
                continue;
            }

            class_exists($className);
            foreach ($this->getProvidedClasses(new \ReflectionClass($className)) as $providingClassName) {
                $qualifierTags = $definition->getTag('hco.autowire.qualifier');
                $primaryTags   = $definition->getTag('hco.autowire.primary');
                $isPrimary     = count($primaryTags) > 0;
                $qualifier     = count($qualifierTags) > 0 ? reset($qualifierTags)['qualifier'] : null;

                $dependencyRegistry->register($providingClassName, $serviceId, $qualifier, $isPrimary);
            }
        }
        return $dependencyRegistry;
    }
}

