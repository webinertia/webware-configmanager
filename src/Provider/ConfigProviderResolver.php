<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

use ReflectionClass;
use ReflectionException;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Merge\ConfigMerger;

use function array_key_exists;
use function class_exists;
use function get_debug_type;
use function is_array;
use function is_callable;
use function is_string;
use function sprintf;

/**
 * Resolves config provider references to callables and merges what they return.
 *
 * The reference forms and the rejection rules mirror
 * `Laminas\ConfigAggregator\ConfigAggregator`, so a provider list written for that
 * package works here unchanged: a class-string is instantiated with no arguments and
 * must then be callable, a callable is used verbatim, a reference listed twice is an
 * error, and a provider that does not return an array is an error.
 *
 * Merging is delegated to {@see ConfigMerger}, which reproduces the aggregator's own
 * merge, so a provider may steer the merge with {@see \Webware\ConfigManager\Merge\MergeDirective}
 * exactly as a file-based source can.
 *
 * @api
 */
final class ConfigProviderResolver
{
    /**
     * Merge every provider's output into a single configuration array.
     *
     * @param iterable<class-string|callable> $providers
     * @return array<array-key, mixed>
     * @throws Exception\InvalidArgumentException
     */
    public function aggregate(iterable $providers): array
    {
        $config = [];

        foreach ($this->resolve($providers) as $provider) {
            $contribution = $provider();

            if (! is_array($contribution)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Config provider "%s" does not return an array; received "%s"',
                    ProviderReference::of($provider)->label,
                    get_debug_type($contribution),
                ));
            }

            $config = ConfigMerger::merge($config, $contribution);
        }

        return $config;
    }

    /**
     * @param iterable<class-string|callable> $providers
     * @return list<callable>
     * @throws Exception\InvalidArgumentException
     */
    public function resolve(iterable $providers): array
    {
        $resolved = [];
        $seen     = [];

        foreach ($providers as $supplied) {
            $reference = ProviderReference::of($supplied);

            // Duplicates are rejected rather than deduplicated: the same provider merged
            // twice is a configuration bug the caller wants to hear about.
            if (array_key_exists($reference->identity, $seen)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Config provider "%s" was supplied more than once',
                    $reference->label,
                ));
            }

            $seen[$reference->identity] = true;
            $resolved[]                 = $this->resolveOne($reference);
        }

        return $resolved;
    }

    /**
     * @throws Exception\InvalidArgumentException
     */
    private function instantiate(string $class): callable
    {
        if (! class_exists($class)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Config provider class "%s" does not exist',
                $class,
            ));
        }

        // Instantiated with no arguments, matching the aggregator. A provider that needs
        // collaborators is supplied as an instance instead.
        //
        // isInstantiable() is checked before instantiating because an abstract class
        // raises Error rather than ReflectionException, and a class with a non-public
        // constructor raises ReflectionException. Checking first turns both into one
        // reported mistake without catching Error. The required-parameter check does the
        // same for a constructor that demands arguments, which would otherwise escape as
        // an ArgumentCountError.
        try {
            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable()) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Config provider class "%s" cannot be instantiated: it is abstract or '
                        . 'its constructor is not public, so supply an instance instead',
                    $class,
                ));
            }

            $constructor = $reflection->getConstructor();
            $required    = null === $constructor ? 0 : $constructor->getNumberOfRequiredParameters();

            if ($required > 0) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Config provider class "%s" cannot be instantiated without arguments: '
                        . 'its constructor requires %d',
                    $class,
                    $required,
                ));
            }

            $instance = $reflection->newInstance();
        } catch (ReflectionException $exception) {
            // newInstance() declares ReflectionException. isInstantiable() above covers
            // every documented cause, so this is a guard rather than a reachable path;
            // it stays so the public contract reports only our own exception.
            throw new Exception\InvalidArgumentException(sprintf(
                'Config provider class "%s" cannot be instantiated: %s',
                $class,
                $exception->getMessage(),
            ), previous: $exception);
        }

        if (! is_callable($instance)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Config provider must be a class-string or a callable; received "%s"',
                get_debug_type($instance),
            ));
        }

        return $instance;
    }

    /**
     * @throws Exception\InvalidArgumentException
     */
    private function resolveOne(ProviderReference $reference): callable
    {
        if (is_string($reference->value)) {
            return $this->instantiate($reference->value);
        }

        if (! is_callable($reference->value)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Config provider must be a class-string or a callable; received "%s"',
                get_debug_type($reference->value),
            ));
        }

        return $reference->value;
    }
}
