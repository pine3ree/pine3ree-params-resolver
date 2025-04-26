<?php

/**
 * @author pine3ree https://github.com/pine3ree
 * @package p3im
 * @subpackage p3im-app
 */

namespace pine3ree\Container;

use Psr\Container\ContainerInterface;
use pine3ree\Container\ParamsResolver;

class ParamsResolverFactory
{
    public function __invoke(ContainerInterface $container) : ParamsResolver
    {
        return new ParamsResolver($container);
    }
}
