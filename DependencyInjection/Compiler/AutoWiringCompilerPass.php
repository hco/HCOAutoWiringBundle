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
        $annotationReader = $container->get('annotation_reader');

        $autoWiredServices  = $this->findAutoWiredServices($container);
        $dependencyRegistry = $this->buildDependencyRegistry($container);

        foreach ($autoWiredServices as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class      = new \ReflectionClass($definition->getClass());
            $container->addResource(new FileResource($class->getFileName()));

            $definition->setArguments(
                $this->getAutowiredArguments($annotationReader, $class, $dependencyRegistry)
            );
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

            foreach ($this->getProvidedClasses(new \ReflectionClass($className)) as $providingClassName) {
                $isPrimary = $definition->hasTag('hco.autowire.primary');
                $qualifier = null;

                if ($definition->hasTag('hco.autowire.qualifier')) {
                    $qualifierTags     = $definition->getTag('hco.autowire.qualifier');
                    $firstQualifierTag = reset($qualifierTags);
                    $qualifier         = $firstQualifierTag['qualifier'];
                }

                $dependencyRegistry->register($providingClassName, $serviceId, $qualifier, $isPrimary);
            }
        }
        return $dependencyRegistry;
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return array
     */
    public function findAutoWiredServices(ContainerBuilder $container)
    {
        return array_keys(
            $container->findTaggedServiceIds(
                'hco.autowire'
            )
        );
    }

    /**
     * @param AnnotationReader $annotationReader
     * @param \ReflectionClass $class
     * @param DependencyRegistry $dependencyRegistry
     *
     * @return array
     */
    private function getAutowiredArguments(
        AnnotationReader $annotationReader,
        \ReflectionClass $class,
        DependencyRegistry $dependencyRegistry
    ) {
        $newArguments = array();

        $qualifiers = $this->getQualifiersForParameters($annotationReader, $class);

        foreach ($class->getConstructor()->getParameters() as $parameter) {
            if (isset($qualifiers[$parameter->getName()])) {
                // Parameter is qualified
                $newArguments[] = new Reference(
                    $dependencyRegistry->findQualified(
                        $parameter->getClass()->getName(),
                        $qualifiers[$parameter->getName()]
                    )
                );
            } else {
                // Parameter is not qualified
                $newArguments[] = new Reference(
                    $dependencyRegistry->findUnqualified($parameter->getClass()->getName())
                );
            }
        }
        return $newArguments;
    }
}

