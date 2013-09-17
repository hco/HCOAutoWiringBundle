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

class AutoWiringCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $dependencyRegistry = new DependencyRegistry();
        /** @var AnnotationReader $annotationReader */
        $annotationReader = $container->get('annotation_reader');

        foreach ($container->getDefinitions() as $serviceId => $definition) {
            $className = $container->getParameterBag()->resolveValue(
                $definition->getClass()
            );

            if ($className === null) {
                continue;
            }

            class_exists($className);
            foreach ($this->getProvidedClasses(new \ReflectionClass($className)) as $providingClassName) {
                $annotation = $annotationReader->getClassAnnotation(
                    new \ReflectionClass($className),
                    'HCO\AutoWiringBundle\Annotation\Qualifier'
                );

                $qualifierTags = $definition->getTag('hco.autowire.qualifier');
                $primaryTags = $definition->getTag('hco.autowire.primary');
                $isPrimary = count($primaryTags) > 0;
                $qualifier = count($qualifierTags) > 0 ? reset($qualifierTags)['qualifier'] : null;

                $dependencyRegistry->register($providingClassName, $serviceId, $qualifier, $isPrimary);
            }
        }

        $taggedServices = $container->findTaggedServiceIds(
            'hco.autowire'
        );

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
}

