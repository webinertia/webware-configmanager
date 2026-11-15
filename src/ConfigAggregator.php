<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Webware\ConfigManager\Cache\ConfigCache;
use Webware\ConfigManager\Provider\ConfigProviderResolver;

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
     * @param iterable<class-string|callable> $providers
     * @param null|string $cachedConfigFile Cache file path, or null to disable the cache.
     * @throws Exception\InvalidArgumentException If a provider is not usable.
     * @throws Exception\RuntimeException If the cache cannot be read or written.
     */
    public function __construct(iterable $providers = [], ?string $cachedConfigFile = null)
    {
        $cache  = new ConfigCache($cachedConfigFile);
        $cached = $cache->load();

        // A cache that is present is authoritative: the providers do not run at all. That
        // is deliberate parity with the aggregator, and the reason disabling the cache in
        // configuration does not take effect until the file is deleted.
        if (null !== $cached) {
            $this->config = $cached;

            return;
        }

        $this->config = new ConfigProviderResolver()->aggregate($providers);

        $cache->save($this->config);
    }

    /** @return array<array-key, mixed> */
    public function getMergedConfig(): array
    {
        return $this->config;
    }
}
