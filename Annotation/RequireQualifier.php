<?php

namespace HCO\AutoWiringBundle\Annotation;

/**
 * Annotation to require a specific qualifier during autowiring.
 *
 * @Annotation
 * @Target("METHOD")
 */
class RequireQualifier
{
    public $param;
    public $qualifier;
}
