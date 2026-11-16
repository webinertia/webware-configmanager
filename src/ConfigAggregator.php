<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Override;
use Webware\ConfigManager\Cache\ConfigCache;
use Webware\ConfigManager\Processor\ProcessorResolver;
use Webware\ConfigManager\Provider\ConfigProviderResolver;

/**
 * Merges configuration from a list of providers into a read-only Config.
 *
 * This is the drop-in replacement for `Laminas\ConfigAggregator\ConfigAggregator`: the
 * constructor takes the same arguments in the same order and `getMergedConfig()` returns
 * the same array, so a consumer swaps the class name in its `config/config.php` and its
 * provider list keeps working. The provider contract is unchanged, including the
 * `PhpFileProvider` and `ArrayProvider` equivalents in this package.
 *
 * It extends {@see Config}, so the merged result is the configuration rather than a
 * separate array that has to be wrapped to be used. A provider returns an array and a
 * Config consumes one; aggregation is how that array comes to exist, not a second
 * container for it. So the merged configuration is readable through the same accessors
 * as any other Config: `get()`, array access, iteration, `count()` and `toArray()`.
 * `getMergedConfig()` remains for the aggregator's own signature.
 *
 * The two cache constants are redeclared here rather than only on
 * {@see ConfigCache}, so an application's existing references to
 * `ConfigAggregator::ENABLE_CACHE` and `ConfigAggregator::CACHE_FILEMODE` keep resolving
 * after the class name is changed.
 *
 * Pre-processors run before ordering, because they transform the provider list and their
 * output becomes the order that ordering refines. Post-processors run after the merge,
 * because they act on the merged array. A processor is a callable, or a class-string that
 * is instantiable with no arguments, exactly as in the aggregator.
 *
 * @api
 */
final class ConfigAggregator extends Config
{
    /** Truthy in the merged config to allow the cache to be written. */
    public const string ENABLE_CACHE = ConfigCache::ENABLE_CACHE;

    /** Optional file mode applied to the cache file. */
    public const string CACHE_FILEMODE = ConfigCache::CACHE_FILEMODE;

    /**
     * The parameter order is the aggregator's, including `postProcessors` before
     * `preProcessors`. That order is unintuitive and is copied deliberately, so a caller
     * using positional arguments does not have their processors silently swapped.
     *
     * The merged configuration is read-only, matching a Config built for reading.
     *
     * @param iterable<class-string|callable> $providers
     * @param null|string $cachedConfigFile Cache file path, or null to disable the cache.
     * @param list<class-string|callable> $postProcessors callable(array): array
     * @param list<class-string|callable> $preProcessors callable(iterable): iterable
     * @throws Exception\InvalidArgumentException If a provider or processor is not usable.
     * @throws Exception\RuntimeException If the cache cannot be read or written.
     */
    public function __construct(
        iterable $providers = [],
        ?string $cachedConfigFile = null,
        array $postProcessors = [],
        array $preProcessors = [],
    ) {
        $cache  = new ConfigCache($cachedConfigFile);
        $cached = $cache->load();

        // A cache that is present is authoritative: the providers do not run at all. That
        // is deliberate parity with the aggregator, and the reason disabling the cache in
        // configuration does not take effect until the file is deleted.
        if (null !== $cached) {
            parent::__construct($cached);

            return;
        }

        $merged = self::postProcess(
            $postProcessors,
            new ConfigProviderResolver()->aggregate(self::preProcess($preProcessors, $providers)),
        );

        parent::__construct($merged);

        $cache->save($merged);
    }

    /**
     * Let each post-processor transform the merged configuration.
     *
     * @param list<class-string|callable> $processors
     * @param array<array-key, mixed> $config
     * @return array<array-key, mixed>
     * @throws Exception\InvalidArgumentException
     */
    private static function postProcess(array $processors, array $config): array
    {
        $resolver = new ProcessorResolver();

        foreach ($processors as $processor) {
            $config = Shape::array(
                value  : $resolver->resolve($processor)($config),
                subject: 'A config post-processor',
            );
        }

        return $config;
    }

    /**
     * Let each pre-processor transform the provider list.
     *
     * @param list<class-string|callable> $processors
     * @param iterable<class-string|callable> $providers
     * @return iterable<class-string|callable>
     * @throws Exception\InvalidArgumentException
     */
    private static function preProcess(array $processors, iterable $providers): iterable
    {
        $resolver = new ProcessorResolver();

        foreach ($processors as $processor) {
            $providers = Shape::providerList(
                value  : $resolver->resolve($processor)($providers),
                subject: 'A config pre-processor',
            );
        }

        return $providers;
    }

    /**
     * Return the merged configuration as an array.
     *
     * Kept for the aggregator's own signature. The data lives in the Config this class
     * extends, so the array is derived rather than stored a second time.
     *
     * @return array<array-key, mixed>
     */
    public function getMergedConfig(): array
    {
        return $this->toArray();
    }

    /**
     * A nested value of a merged config is an ordinary read-only Config.
     *
     * The aggregator cannot build itself from an array, because its constructor takes a
     * provider list, so the base implementation is not usable here.
     *
     * @param array<array-key, mixed> $array
     */
    #[Override]
    protected function newNested(array $array, bool $allowModifications): Config
    {
        return new Config($array, $allowModifications);
    }
}
