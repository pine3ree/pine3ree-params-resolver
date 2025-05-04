<?php

/**
 * @package pine3ree-params-resolver
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Container;

use Throwable;

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
     * Resolve a function/method/invokable-object's arguments values using provided
     * resolved params or retrieving them from the container
     *
     * @param string|array{0: object|class-string, 1: string}|object $callable
     *      An [object/class-string, method] array expression, a function or an invokable
     *      object. Use [fqcn, '__construct'] for class constructors.
     * @param array<string, mixed> $resolvedParams Optional resolved parameter values
     *      indexed by FQCN/FQIN/container-service-name or by parameter name
     * @return array<mixed>
     * @throws Throwable
     */
    public function resolve($callable, ?array $resolvedParams = null): array;
}
