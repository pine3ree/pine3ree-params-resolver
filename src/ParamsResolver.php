<?php

/**
 * @package pine3ree-container-params-resolver
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Throwable;
use pine3ree\Container\ParamsResolverInterface;

use function class_exists;
use function function_exists;
use function get_class;
use function interface_exists;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;

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

    /**
     * A cache of resolved reflection parameters indexed by function/class::method name
     *
     * @var array<string, ReflectionParameter[]>
     */
    private static $cache = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Try to resolve parameters and argument values
     *
     * @param string|array{0: object|string, 1: string}|object $callable
     *      An [object/class, method] array expression, a function or an invokable
     *      object. Use [fqcn, '__construct'] for class constructors.
     * @param array<mixed>|null $resolvedParams Known parameter values indexed by
     *      class/interface name, container service-name or parameter name
     * @return array<mixed>
     * @throws RuntimeException
     */
    public function resolve($callable, ?array $resolvedParams = null): array
    {
        $rf_params = $this->resolveReflectionParameters($callable);

        if (empty($rf_params)) {
            return [];
        }

        return $this->resolveArguments($rf_params, $resolvedParams);
    }

    /**
     * Try to resolve reflection parameters for given method/closure/invokable
     *
     * @param string|array{0: object|string, 1: string}|object $callable
     *      An [object/class, method] array expression, a function or an invokable
     *      object. Use [fqcn, '__construct'] for class constructors.
     * @return ReflectionParameter[]
     * @throws RuntimeException
     */
    private function resolveReflectionParameters($callable): array
    {
        // Case: callable array specs [object/class, method]
        if (is_array($callable)) {
            $object = $callable[0] ?? null; // @phpstan-ignore-line
            $method = $callable[1] ?? null; // @phpstan-ignore-line
            if (empty($method) || !is_string($method)) {
                throw new RuntimeException(
                    "An invalid method value was provided in element {1} of the callable array specs!"
                );
            }
            if (empty($object)) {
                throw new RuntimeException(
                    "An empty object/class value was provided in element {0} of the callable array specs!"
                );
            }
            if (is_object($object)) {
                $class = get_class($object);
            } elseif (is_string($object)) {
                $class = $object;
                if (!class_exists($class)) {
                    throw new RuntimeException(
                        "A class named `{$class}` is not defined for given callable!"
                    );
                }
            } else {
                throw new RuntimeException(
                    "An invalid object/class value was provided in element {0} of the callable array specs!"
                );
            }

            // Try cached reflection parameters first, if any
            $cm_key = "{$class}::{$method}";
            $rm_params = self::$cache[$cm_key] ?? null;
            if ($rm_params === null) {
                $rc = new ReflectionClass($class);
                if ($rc->hasMethod($method)) {
                    $rm = $method === '__construct' ? $rc->getConstructor() : $rc->getMethod($method);
                    if ($rm instanceof ReflectionMethod) {
                        $rm_params = $rm->getParameters();
                        self::$cache[$cm_key] = $rm_params;
                        return $rm_params;
                    }
                }
                throw new RuntimeException(
                    "A method named `{$class}::{$method}` is not defined for given callable!"
                );
            }

            return $rm_params;
        }

        if (is_object($callable)) {
            // Case: anonymous/arrow function
            if ($callable instanceof Closure) {
                $rf = new ReflectionFunction($callable);
                return $rf->getParameters();
            }
            // Case: invokable object
            if (method_exists($callable, $method = '__invoke')) {
                /** @var object $callable Already ensured to be a an object by the conditional */
                // Try cached reflection parameters first, if any
                $class = get_class($callable);
                $cm_key = "{$class}::{$method}";
                $rm_params = self::$cache[$cm_key] ?? null;
                if ($rm_params === null) {
                    $rm = new ReflectionMethod($class, $method);
                    $rm_params = $rm->getParameters();
                    self::$cache[$cm_key] = $rm_params;
                }

                return $rm_params;
            }

            throw new RuntimeException(
                "The provided callable argument is an object but is not invokable!"
            );
        }

        // Case: function
        if (is_string($callable) && function_exists($callable)) {
            $rf_params = self::$cache[$callable] ?? null;
            if ($rf_params === null) {
                $rf = new ReflectionFunction($callable);
                $rf_params = $rf->getParameters();
                self::$cache[$callable] = $rf_params;
            }

            return $rf_params;
        }

        throw new RuntimeException(
            "Cannot fetch a reflection method or function for given callable!"
        );
    }

    /**
     * Resolve the argument values using injected params, the container dependencies
     * and values, default values, if any, or the NULL value for nullable parameters
     *
     * @param ReflectionParameter[] $rf_params
     * @param array<mixed>|null $resolvedParams Known parameter values indexed by
     *      class/interface name, container service-name or parameter name
     * @return array<mixed>
     * @throws RuntimeException
     */
    private function resolveArguments(array $rf_params, ?array $resolvedParams = null): array
    {
        $container = $this->container;

        // Build the arguments for the provided ~callable~
        $args = [];
        /** @var ReflectionParameter $rp */
        foreach ($rf_params as $rp) {
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
                                . " with name `{$rp_name}` and class `{$rp_fqcn}`"
                                . " for given callable!"
                            );
                        }
                    } else {
                        throw new RuntimeException(
                            "Unable to resolve the dependency parameter with name `{$rp_name}`"
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
                    "Unable to resolve the parameter with name `{$rp_name}` for given callable!",
                );
            }
        }

        return $args;
    }

    /**
     * @internal Used by internally by unit tests
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the cached reflection parameters for given callable key, if any.
     *
     * @internal Used for debug
     *
     * @return ReflectionParameter[]|null
     */
    public static function getCachedReflectionParameters(string $key): ?array
    {
        return self::$cache[$key] ?? null;
    }
}
