<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Webware\ConfigManager\Container\AbstractConfigFactory;
use Webware\ConfigManager\Container\ReaderPluginManager;
use Webware\ConfigManager\Container\ReaderPluginManagerFactory;
use Webware\ConfigManager\Container\WriterPluginManager;
use Webware\ConfigManager\Container\WriterPluginManagerFactory;

/**
 * Wiring entry point for the package.
 *
 * Declared under `extra.laminas.config-provider` in composer.json, so a consumer's
 * config aggregator merges this without any further registration.
 *
 * The two plugin manager factories build the ServiceManager-backed managers, which
 * carry the plugin validation the standalone defaults do not. Wiring them does not by
 * itself route the static `Factory` through the container: a consumer that wants that
 * passes the resolved manager to {@see Factory::setReaderPluginManager()} or
 * {@see Factory::setWriterPluginManager()}.
 *
 * @type DependenciesConfig array{
 *     abstract_factories?: list<class-string>,
 *     factories: array<class-string, class-string>,
 * }
 * @type ProviderConfig array{
 *     dependencies: DependenciesConfig,
 * }
 * @internal
 */
final class ConfigProvider
{
    /** @return DependenciesConfig */
    public function getDependencies(): array
    {
        return [
            // Abstract factories are consulted for every service that has no explicit
            // factory, so registering one costs a preg_match loop per unresolved name.
            // Uncomment to make configuration subtrees injectable: with this entry in
            // place, a service named `config-anything`, `anything\Config` or
            // `anything-config` resolves to $config['anything'] from the `config`
            // service, provided that entry is an array and is not null.
            'abstract_factories' => [
                // AbstractConfigFactory::class,
            ],
            'factories'          => [
                ReaderPluginManager::class => ReaderPluginManagerFactory::class,
                WriterPluginManager::class => WriterPluginManagerFactory::class,
            ],
        ];
    }

    /** @return ProviderConfig */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }
}
