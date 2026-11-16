<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Builds the ServiceManager-backed writer plugin manager, using the consuming
 * container as its creation context.
 *
 * @internal
 */
final class WriterPluginManagerFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): WriterPluginManager
    {
        // The plugin tables are a top-level key of the aggregated config, named after this
        // manager. They are not a service: asking the container for
        // WriterPluginManager::class re-enters this factory, because that name is what
        // dependencies.factories maps to this class.
        /** @var array<string, mixed> $config */
        $config = $container->has('config') ? $container->get('config') : [];

        /** @var array<array-key, mixed> $tables */
        $tables = $config[WriterPluginManager::class] ?? [];

        return new WriterPluginManager($container, $tables);
    }
}
