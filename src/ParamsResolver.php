<?php

/**
 * @package pine3ree-params-resolver
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Container;

use Psr\Container\ContainerInterface;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Throwable;
use pine3ree\Container\ParamsResolverInterface;
use pine3ree\Helper\Reflection;

use function class_exists;
use function function_exists;
use function get_class;
use function interface_exists;
use function is_array;
use function is_object;
use function is_string;

/**
 * ParamsResolver try to resolve parameters and related argument values for object/class-methods,
 * functions, anonymous-functions and invokable-objects using provided values or
 * using the composed container
 */
class ParamsResolver implements ParamsResolverInterface
{
    /**
     * The container used to resolve the parameters that represent dependencies
     */
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Try to resolve parameters and argument values
     *
     * @param string|array{0: object|class-string, 1: string}|object $callable
     *      An [object/class, method] array expression, a function or an invokable
     *      object. Use [fqcn, '__construct'] for class constructors.
     * @param array<mixed>|null $resolvedParams Known parameter values indexed by
     *      class/interface name, container service-name or parameter name
     * @return array<mixed>
     * @throws RuntimeException
     */
    public function resolve($callable, ?array $resolvedParams = null): array
    {
        $rp_params = $this->resolveReflectionParameters($callable);

        if (empty($rp_params)) {
            return [];
        }

        return $this->resolveArguments($rp_params, $resolvedParams);
    }

    /**
     * Try to resolve reflection parameters for given method/closure/invokable
     *
     * @param string|array{0: object|class-string, 1: string}|object $callable
     *      An [object/class, method] array expression, a function or an invokable
     *      object. Use [fqcn, '__construct'] for class constructors.
     * @return ReflectionParameter[]
     * @throws RuntimeException
     */
    private function resolveReflectionParameters($callable): array
    {
        // Case: callable array specs [object|class-string, method]
        if (is_array($callable)) {
            $object = $callable[0] ?? null; // @phpstan-ignore-line
            $method = $callable[1] ?? null; // @phpstan-ignore-line
            if (empty($method) || !is_string($method)) {
                throw new RuntimeException(
                    "An invalid 'method' value was provided in element {1} of the callable array specs!"
                );
            }
            if (empty($object) || !(is_object($object) || is_string($object))) {
                throw new RuntimeException(
                    "An empty 'object/class-string' value was provided in element {0} of the callable array specs!"
                );
            }
            $rm_params = Reflection::getParametersForMethod($object, $method, true);
            if ($rm_params === null) {
                $class = is_object($object) ? get_class($object) : $object;
                throw new RuntimeException(
                    "Unable to resolve reflection parameters for method `{$class}::{$method}`"
                );
            }
            return $rm_params;
        }

        // Case: anonymous/arrow function or invokable object
        if (is_object($callable)) {
            $rm_params = Reflection::getParametersForInvokable($callable);
            if ($rm_params === null) {
                throw new RuntimeException(
                    "The provided callable argument is an object but is not invokable!"
                );
            }
            return $rm_params;
        }

        // Case: function
        if (is_string($callable) && function_exists($callable)) {
            $rf_params = Reflection::getParametersForFunction($callable, false);
            if ($rf_params === null) {
                throw new RuntimeException(
                    "Unable to resolve reflection parameters for function `{$callable}`!"
                );
            }
            return $rf_params;
        }

        throw new RuntimeException(
            "Unable to resolve reflection parameters for given callable!"
        );
    }

    /**
     * Resolve the argument values using injected params, the container dependencies
     * and values, default values, if any, or the NULL value for nullable parameters
     *
     * @param ReflectionParameter[] $rp_params
     * @param array<mixed>|null $resolvedParams Known parameter values indexed by
     *      class/interface name, container service-name or parameter name
     * @return array<mixed>
     * @throws RuntimeException
     */
    private function resolveArguments(array $rp_params, ?array $resolvedParams = null): array
    {
        $container = $this->container;

        $args = [];
        /** @var ReflectionParameter $rp */
        foreach ($rp_params as $rp) {
            $rp_name = $rp->getName();
            $rp_type = $rp->getType();
            if ($rp_type instanceof ReflectionNamedType && !$rp_type->isBuiltin()) {
                $rp_fqcn = $rp_type->getName();
                // fqcn/fqin type-hinted arguments
                if (interface_exists($rp_fqcn) || class_exists($rp_fqcn)) {
                    if (isset($resolvedParams[$rp_fqcn])) {
                        // Try injected/resolved params first
                        $args[] = $resolvedParams[$rp_fqcn];
                    } elseif ($rp_fqcn === ContainerInterface::class || $rp_fqcn === get_class($container)) {
                        // A container should not be a type-hinted dependency (service-locator anti-pattern),
                        // but another dependency resolver might use it
                        $args[] = $container;
                    } elseif ($container->has($rp_fqcn)) {
                        // Parameter resolved by the container
                        $args[] = $container->get($rp_fqcn);
                    } elseif ($rp->isDefaultValueAvailable()) {
                        // Dependency with a default value provided
                        $args[] = $rp->getDefaultValue();
                    } elseif ($rp->allowsNull()) {
                        // Nullable dependency
                        $args[] = null;
                    } elseif (class_exists($rp_fqcn)) {
                        // Try instantating with argument-less constructor call
                        try {
                            $args[] = new $rp_fqcn();
                        } catch (Throwable $ex) {
                            throw new RuntimeException(
                                "Unable to instantiate an object for the parameter"
                                . " `{$rp_name}` of type `{$rp_fqcn}` for given callable!"
                            );
                        }
                    } else {
                        throw new RuntimeException(
                            "Unable to resolve the dependency parameter `{$rp_name}`"
                            . " for given callable!"
                        );
                    }
                } else {
                    throw new RuntimeException(
                        "`{$rp_fqcn}` is neither a valid interface nor a class name"
                        . " for given dependency named `{$rp_name}`!",
                    );
                }
            } elseif (isset($resolvedParams[$rp_name])) {
                // Injected parameter matched by name
                $args[] = $resolvedParams[$rp_name];
            } elseif ($container->has($rp_name)) {
                // Injected parameter resolved as container service-id
                $args[] = $container->get($rp_name);
            } elseif ($rp->isDefaultValueAvailable()) {
                // The use a default parameter, if available
                $args[] = $rp->getDefaultValue();
            } elseif ($rp->allowsNull()) {
                // Finally use the NULL value, if the parameter is nullable
                $args[] = null;
            } else {
                throw new RuntimeException(
                    "Unable to resolve the parameter `{$rp_name}` for given callable!",
                );
            }
        }

        return $args;
    }
}
