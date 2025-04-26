<?php

/**
 * @package pine3ree-container-params-resolver
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Container;

use Psr\Container\ContainerInterface;
use pine3ree\Container\ParamsResolver;

class ParamsResolverFactory
{
    public function __invoke(ContainerInterface $container): ParamsResolver
    {
        return new ParamsResolver($container);
    }
}
