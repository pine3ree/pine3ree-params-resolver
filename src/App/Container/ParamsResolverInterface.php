<?php

/**
 * @package     p3im
 * @subpackage  p3im-app
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Container;

use Psr\Container\ContainerInterface;

/**
 * ParamsResolver is a method/function argument resolver that uses either injected
 * resolved values or fetch dependencies from the container
 *
 * Parameters are looked-up by type-hinted class/interface-name or by name.
 * The search starts inside the injected default arguments, will then continue
 * inside the container and finally a default value, if provided, is be used.
 *
 */
interface ParamsResolverInterface
{
    /**
     * Resolve a function/method/callable-object's arguments using provided resolved
     * params or retrieving them from the container
     *
     * The resolver usually composes a container itself, but this can be overriden
     * by using an alternative container provided as argument
     *
     * @param string|array|object $callable An [object/class, method] array expression,
     *      a function or an invokable object. Use [fqcn, '__construct'] for
     *      class constructors.
     * @param array $resolvedParams Optionally injected resolved params indexed by FQCN/FQIN/container-service-ID
     * @param ContainerInterface|null $container Optional alternative container for dependency resolution
     * @return array
     * @throws RuntimeException
     */
    public function resolve($callable, array $resolvedParams = null, ?ContainerInterface $container = null): array;
}
