<?php
namespace HCO\AutoWiringBundle\Tests\Helpers;

use HCO\AutoWiringBundle\Annotation\RequireQualifier;

class ServiceDependingOnQualifiedService
{
    /**
     * @var ServiceWithDependencyAndAnnotation
     */
    public $dependency;

    /**
     * @param \StdClass $dependency
     * @RequireQualifier(param="dependency", qualifier="master")
     */
    public function __construct(\StdClass $dependency)
    {

        $this->dependency = $dependency;
    }
}
