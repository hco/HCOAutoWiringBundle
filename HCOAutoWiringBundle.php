<?php

namespace HCO\AutoWiringBundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use HCO\AutoWiringBundle\DependencyInjection\Compiler;


class HCOAutoWiringBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new Compiler\AutoWiringCompilerPass());
    }
}
