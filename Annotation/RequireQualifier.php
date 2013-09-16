<?php

namespace HCO\AutoWiringBundle\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
class RequireQualifier
{
    public $param;
    public $qualifier;
}
