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

use function array_keys;
use function class_exists;
use function function_exists;
use function get_class;
use function interface_exists;
use function is_array;
use function is_object;
use function is_string;
use function json_encode;
use function method_exists;
use function sprintf;

class ParamsResolver implements ParamsResolverInterface
{
    /**
     * The container used to resolve the parameters that represent dependencies
     */
    private ContainerInterface $container;

    /**
     * A cache of resolved reflection parameters indexed by function/method name
     *
     * @var array<string, ReflectionParameter[]>
     */
    private static $rf_params = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function resolve($callable, array $resolvedParams = null, ?ContainerInterface $container = null): array
    {
        // Invokable objects
        $is_object = is_object($callable);

        if ($is_object && ! $callable instanceof Closure && method_exists($callable, '__invoke')) {
            $callable = [$callable, '__invoke'];
        }

        // Fetch the reflection function/method parameters
        if (is_array($callable)) {
            $object = $callable[0];
            $method = $callable[1];
            $class = is_object($object) ? get_class($object) : $object;
            $cache_key = "{$class}::{$method}";
            $rf_params = self::$rf_params[$cache_key] ?? null;
            if ($rf_params === null) {
                if ($method === '__construct') {
                    $rc = new ReflectionClass($class);
                    $rm = $rc->getConstructor();
                } else {
                    $rm = new ReflectionMethod($object, $method);
                }
                self::$rf_params[$cache_key] = $rf_params = $rm->getParameters();
            }
        } elseif (is_string($callable) && function_exists($callable)) {
            $rf_params = self::$rf_params[$callable] ?? null;
            if ($rf_params === null) {
                $rf = new ReflectionFunction($callable);
                self::$rf_params[$callable] = $rf_params = $rf->getParameters();
            }
        } elseif ($is_object && $callable instanceof Closure) {
            $rf = new ReflectionFunction($callable);
            $rf_params = $rf->getParameters();
        } else {
            throw new RuntimeException(sprintf(
                "Cannot fetch a reflection method or function for given callable %s !",
                json_encode($callable)
            ));
        }

        $container = $container ?? $this->container;

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
                    } elseif (class_exists($rp_fqcn)) {
                        // Try instantating with argument-less constructor call
                        try {
                            $args[] = new $rp_fqcn();
                        } catch (Throwable $ex) {
                            throw new RuntimeException(sprintf(
                                "Unable to instantiate an object for the parameter"
                                . " with name `{$rp_name}` and class `{$rp_fqcn}`"
                                . " for given callable %s!",
                                json_encode($callable)
                            ));
                        }
                    } else {
                        throw new RuntimeException(sprintf(
                            "Unable to resolve the dependency parameter with name `{$rp_name}`"
                            . " for given callable %s!",
                            json_encode($callable)
                        ));
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
                // Finally try a default parameter, if available
                $args[] = $rp->getDefaultValue();
            } else {
                throw new RuntimeException(sprintf(
                    "Unable to resolve the parameter with name `{$rp_name}` for given callable %s!",
                    json_encode($callable)
                ));
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
        return self::$rf_params[$key] ?? null;
    }
}
