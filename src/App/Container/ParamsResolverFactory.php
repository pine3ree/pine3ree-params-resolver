<?php

/**
 * @author pine3ree https://github.com/pine3ree
 * @package p3im
 * @subpackage p3im-app
 */

namespace App\Action;

use App\Container\ParamsResolver;
use Psr\Container\ContainerInterface;

class ParamsResolverFactory
{
    public function __invoke(ContainerInterface $container) : ParamsResolver
    {
        return new ParamsResolver($container);
    }
}
