<?php

namespace Stacey;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CoreBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new ContainerExtension();
    }
}
