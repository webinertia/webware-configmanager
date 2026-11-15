<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Webware\ConfigManager\Cache\ConfigCache;
use Webware\ConfigManager\Processor\ProcessorResolver;
use Webware\ConfigManager\Provider\ConfigProviderResolver;

use function get_debug_type;
use function is_array;
use function is_iterable;
use function sprintf;

/**
 * Merges configuration from a list of providers, optionally through a cache file.
 *
 * This is the drop-in replacement for `Laminas\ConfigAggregator\ConfigAggregator`: the
 * constructor takes the same arguments in the same order and `getMergedConfig()` returns
 * the same shape, so a consumer swaps the class name in its `config/config.php` and its
 * provider list keeps working. The provider contract is unchanged, including the
 * `PhpFileProvider` and `ArrayProvider` equivalents in this package.
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
final class ConfigAggregator
{
    /** Truthy in the merged config to allow the cache to be written. */
    public const string ENABLE_CACHE = ConfigCache::ENABLE_CACHE;

    /** Optional file mode applied to the cache file. */
    public const string CACHE_FILEMODE = ConfigCache::CACHE_FILEMODE;

    /** @var array<array-key, mixed> */
    private readonly array $config;

    /**
     * The parameter order is the aggregator's, including `postProcessors` before
     * `preProcessors`. That order is unintuitive and is copied deliberately, so a caller
     * using positional arguments does not have their processors silently swapped.
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
            $this->config = $cached;

            return;
        }

        $this->config = self::postProcess(
            $postProcessors,
            new ConfigProviderResolver()->aggregate(self::preProcess($preProcessors, $providers)),
        );

        $cache->save($this->config);
    }

    /**
     * @return array<array-key, mixed>
     * @throws Exception\InvalidArgumentException
     */
    private static function asConfig(mixed $config): array
    {
        if (! is_array($config)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'A config post-processor must return an array; it returned "%s"',
                get_debug_type($config),
            ));
        }

        return $config;
    }

    /**
     * @return iterable<class-string|callable>
     * @throws Exception\InvalidArgumentException
     */
    private static function asProviderList(mixed $providers): iterable
    {
        if (! is_iterable($providers)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'A config pre-processor must return an iterable of providers; it returned "%s"',
                get_debug_type($providers),
            ));
        }

        /** @var iterable<class-string|callable> $providers */
        return $providers;
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
            $config = self::asConfig($resolver->resolve($processor)($config));
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
            $providers = self::asProviderList($resolver->resolve($processor)($providers));
        }

        return $providers;
    }

    /** @return array<array-key, mixed> */
    public function getMergedConfig(): array
    {
        return $this->config;
    }
}
