<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Psr\Container\ContainerInterface;
use Webware\ConfigManager\WriterPluginManager;

/**
 * Builds the ServiceManager-backed writer plugin manager, using the consuming
 * container as its creation context.
 *
 * @internal
 */
final class WriterPluginManagerFactory
{
    public function __invoke(ContainerInterface $container): WriterPluginManager
    {
        return new WriterPluginManager($container);
    }
}
