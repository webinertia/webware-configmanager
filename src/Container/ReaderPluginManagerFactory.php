<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Psr\Container\ContainerInterface;

/**
 * Builds the ServiceManager-backed reader plugin manager, using the consuming
 * container as its creation context.
 *
 * @internal
 */
final class ReaderPluginManagerFactory
{
    public function __invoke(ContainerInterface $container): ReaderPluginManager
    {
        return new ReaderPluginManager($container);
    }
}
