<?php

/**
 * @package pine3ree-params-resolver
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Container;

use Psr\Container\ContainerInterface;
use pine3ree\Container\ParamsResolver;
use pine3ree\Container\ParamsResolverInterface;

class ParamsResolverFactory
{
    public function __invoke(ContainerInterface $container): ParamsResolverInterface
    {
        return new ParamsResolver($container);
    }
}
