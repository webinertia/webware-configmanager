<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Builds the ServiceManager-backed reader plugin manager, using the consuming
 * container as its creation context.
 *
 * @internal
 */
final class ReaderPluginManagerFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ReaderPluginManager
    {
        // See WriterPluginManagerFactory: the tables are a top-level key of the aggregated
        // config named after this manager, not a service. Getting this class name from the
        // container re-enters this factory.
        /** @var array<string, mixed> $config */
        $config = $container->has('config') ? $container->get('config') : [];

        /** @var array<array-key, mixed> $tables */
        $tables = $config[ReaderPluginManager::class] ?? [];

        return new ReaderPluginManager($container, $tables);
    }
}
