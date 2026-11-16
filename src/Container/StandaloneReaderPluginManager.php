<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Override;
use Psr\Container\ContainerInterface;
use Webware\ConfigManager\ConfigProvider;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader;

use function array_key_exists;
use function sprintf;

/**
 * Container for reader plugins that does not require a service manager.
 *
 * Implements PSR-11 2.x, so identifiers are typed strings. A reader is identified
 * either by its fully qualified class name or by its format extension, which is
 * registered as an alias: `ini`, `json`, `xml`, `yaml`, `yml`, `properties`.
 *
 * The tables come from {@see ConfigProvider}, the same source the ServiceManager-backed
 * manager is configured from, so the two cannot offer different plugin sets. Nothing here
 * names a reader or a factory: the factories table already maps each class to the factory
 * that builds it, and this class calls the mapped factory with itself as the creation
 * context, which is what a service manager would do.
 *
 * @import-type ReaderPluginManagerConfig from ConfigProvider
 * @internal
 */
final class StandaloneReaderPluginManager implements ContainerInterface
{
    /** @var ReaderPluginManagerConfig */
    private readonly array $tables;

    public function __construct(ConfigProvider $provider = new ConfigProvider())
    {
        $this->tables = $provider->getReaderDependencies();
    }

    /**
     * @throws Exception\InvalidArgumentException
     * @throws Exception\PluginNotFoundException
     */
    #[Override]
    public function get(string $id): Reader\ReaderInterface
    {
        if (! $this->has($id)) {
            throw new Exception\PluginNotFoundException(sprintf('Config reader plugin by name %s not found', $id));
        }

        $reader = ProviderFactory::create(
            class: $this->tables['aliases'][$id] ?? $id,
            label: 'Config reader',
        );

        return $reader instanceof Reader\ReaderInterface
            ? $reader
            : throw new Exception\InvalidArgumentException(sprintf('Config reader "%s" is not a reader', $id));
    }

    #[Override]
    public function has(string $id): bool
    {
        return array_key_exists($this->tables['aliases'][$id] ?? $id, $this->tables['factories']);
    }
}
