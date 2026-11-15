<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

use Generator;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Merge\ConfigMerger;
use Webware\ConfigManager\Support\NoArgumentInstantiator;

use function array_key_exists;
use function array_map;
use function get_debug_type;
use function is_array;
use function is_callable;
use function is_string;
use function iterator_to_array;
use function sprintf;

/**
 * Resolves config provider references to callables and merges what they return.
 *
 * The reference forms and the rejection rules mirror
 * `Laminas\ConfigAggregator\ConfigAggregator`, so a provider list written for that
 * package works here unchanged: a class-string is instantiated with no arguments and
 * must then be callable, a callable is used verbatim, a reference listed twice is an
 * error, and a provider that does not return an array is an error. A provider may also
 * stream its contributions, in which case each value yielded must be an array.
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
     * @return array<array-key, mixed>
     * @throws Exception\InvalidArgumentException
     */
    private static function asConfig(callable $provider, mixed $contribution): array
    {
        if (! is_array($contribution)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Config provider "%s" does not return an array; received "%s"',
                ProviderReference::of($provider)->label,
                get_debug_type($contribution),
            ));
        }

        return $contribution;
    }

    /**
     * Normalise a streamed contribution, validating each item as it passes.
     *
     * array_map is used rather than a foreach so the item type comes from the callback's
     * declared return type. Iterating the Generator directly leaves each yielded value
     * untyped, and validating an untyped value needs a variable to hold it.
     *
     * @return array<array-key, array<array-key, mixed>>
     * @throws Exception\InvalidArgumentException
     */
    private static function flattenStream(callable $provider, Generator $stream): array
    {
        return array_map(
            /**
             * @return array<array-key, mixed>
             * @throws Exception\InvalidArgumentException
             */
            static fn(mixed $contribution): array => self::asConfig($provider, $contribution),
            iterator_to_array($stream, preserve_keys: false),
        );
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array<array-key, mixed>
     * @throws Exception\InvalidArgumentException
     */
    private static function mergeStream(array $config, callable $provider, Generator $stream): array
    {
        foreach (self::flattenStream($provider, $stream) as $contribution) {
            $config = ConfigMerger::merge($config, $contribution);
        }

        return $config;
    }

    /**
     * Merge every provider's output into a single configuration array.
     *
     * Providers are ordered by their own declarations before anything runs, so a package
     * installed last does not lose the keys it shares with a package it builds on. See
     * {@see ProviderOrderResolver}.
     *
     * @param iterable<class-string|callable> $providers
     * @return array<array-key, mixed>
     * @throws Exception\InvalidArgumentException
     */
    public function aggregate(iterable $providers): array
    {
        $config = [];

        foreach ($this->resolve(new ProviderOrderResolver()->order($providers)) as $provider) {
            $contribution = $provider();

            // A provider may stream several arrays rather than return one, which is how
            // PhpFileProvider contributes one array per matched file. mergeStream()
            // re-checks every item it receives, so a provider that yields something
            // other than an array is still reported.
            if ($contribution instanceof Generator) {
                $config = self::mergeStream($config, $provider, $contribution);

                continue;
            }

            $config = ConfigMerger::merge($config, self::asConfig($provider, $contribution));
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
     * Turn one supplied reference into a callable.
     *
     * A class-string is instantiated with no arguments, matching the aggregator, and must
     * then be callable. Anything else is used verbatim. Both failures are reported with
     * the message the aggregator used, so a provider list that worked there fails here
     * for the same reason with the same wording.
     *
     * @throws Exception\InvalidArgumentException
     */
    private function resolveOne(ProviderReference $reference): callable
    {
        /** @var mixed $candidate */
        $candidate = is_string($reference->value)
            ? NoArgumentInstantiator::instantiate(
                class: $reference->value,
                label: 'Config provider',
            )
            : $reference->value;

        if (! is_callable($candidate)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Config provider must be a class-string or a callable; received "%s"',
                get_debug_type($candidate),
            ));
        }

        return $candidate;
    }
}
