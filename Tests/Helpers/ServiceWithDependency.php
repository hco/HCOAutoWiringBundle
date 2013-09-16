<?php

namespace HCO\AutoWiringBundle\Tests\Helpers;

class ServiceWithDependency
{
    public $stdObject;

    public function __construct(\StdClass $stdObject)
    {
        $this->stdObject = $stdObject;
    }
}
