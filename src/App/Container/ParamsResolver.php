<?php

/**
 * @package     p3im
 * @subpackage  p3im-app
 * @author      pine3ree https://github.com/pine3ree
 */

namespace App\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

use function array_keys;
use function class_exists;
use function function_exists;
use function get_class;
use function interface_exists;
use function is_array;
use function is_object;
use function is_string;
use function is_subclass_of;
use function json_encode;
use function method_exists;
use function sprintf;

/**
 * ParamsResolver is a method/function argument resolver
 *
 * Argument are looked-up by name or type-hinted class-name.
 * The search starts inside the injected default parameters, then in the container
 * and then as a last resource a default value, if provided, will be used.
 *
 */
class ParamsResolver
{
    /**
     * The container used to resolve the parameters that represent dependencies
     *
     * @var Container
     */
    private ContainerInterface $container;

    /**
     * A map (action-method to Class::methodName) of methods that have already
     * been resolved
     *
     * @var ReflectionParameter[]
     */
    private static $__r_params = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Resolve a callable arguments using given params or retrieving them from the container
     *
     * @param string|array|object $callable
     * @param array $params Resolved params indexed by fqcn/service-ID
     * @return array
     * @throws RuntimeException
     */
    public function resolve($callable, array $params = null): array
    {
        if (is_object($callable) && method_exists($callable, '__invoke')) {
            $callable = [$callable, '__invoke'];
        }

        // Fetch the reflection function/method parameters
        if (is_array($callable)) {
            $object = $callable[0];
            $method = $callable[1];
            $class = is_object($object) ? get_class($object) : $object;
            if ($class === Closure::class) {
                $rf = new ReflectionMethod($object, $method);
                $__r_params = $rf->getParameters();
            } else {
                $cache_key = "{$class}::{$method}";
                $__r_params = self::$__r_params[$cache_key] ?? null;
                if ($__r_params === null) {
                    $rf = new ReflectionMethod($object, $method);
                    self::$__r_params[$cache_key] = $__r_params = $rf->getParameters();
                }
            }
        } elseif (is_string($callable) && function_exists($callable)) {
            $__r_params = self::$__r_params[$callable] ?? null;
            if ($__r_params === null) {
                $rf = new ReflectionFunction($callable);
                self::$__r_params[$callable] = $__r_params = $rf->getParameters();
            }
        } else {
            throw new RuntimeException(sprintf(
                "Cannot fetch a reflection method or function for given callable %s !",
                json_encode($callable)
            ));
        }

        // Build the arguments for the provided callable
        $args = [];
        foreach ($__r_params as $rp) {
            $rp_name = $rp->getName();
            $rp_type = $rp->getType();
            if ($rp_type instanceof ReflectionNamedType) {
                $rp_fqcn = $rp_type->getName();
            } else {
                $rp_fqcn = null;
            }
            // FQCN type-hinted arguments
            if (isset($rp_fqcn)
                && (class_exists($rp_fqcn, true) || interface_exists($rp_fqcn, true))
            ) {
                // Try injected params first
                if (isset($params[$rp_fqcn])) {
                    $args[] = $params[$rp_fqcn];
                } elseif ($rp_fqcn === Container::class || is_subclass_of($rp_fqcn, Container::class, true)) {
                    $args[] = $this->container;
                } elseif ($this->container->has($rp_fqcn)) {
                    $args[] = $this->container->get($rp_fqcn);
                } else {
                    // Try instantating with argument-less constructor call
                    try {
                        $args[] = new $rp_fqcn();
                    } catch (Exception $ex) {
                        throw new RuntimeException(sprintf(
                            "Unable to create an instance for the parameter with name `%s` for given callable %s !",
                            $rp->getName(),
                            json_encode($callable)
                        ));
                    }
                }
            } elseif (isset($params[$rp_name])) {
                $args[] = $params[$rp_name];
            } elseif ($this->container->has($rp_name)) {
                $args[] = $this->container->get($rp_name);
            } elseif ($rp->isDefaultValueAvailable()) {
                $args[] = $rp->getDefaultValue();
            } else {
                throw new RuntimeException(sprintf(
                    "Unable to resolve the parameter with name `%s` for given callable %s !",
                    $rp->getName(),
                    json_encode($callable)
                ));
            }
        }

        return $args;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public static function getStaticDebugInfo(): array
    {
        return [
            'self::$__r_params' => array_keys(self::$__r_params),
        ];
    }
}
