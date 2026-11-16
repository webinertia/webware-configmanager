<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Override;
use Psr\Container\ContainerInterface;
use Webware\ConfigManager\ConfigProvider;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Writer;

use function array_key_exists;
use function sprintf;

/**
 * Container for writer plugins that does not require a service manager.
 *
 * Implements PSR-11 2.x, so identifiers are typed strings. A writer is identified
 * either by its fully qualified class name or by its format extension, which is
 * registered as an alias: `php`, `ini`, `json`, `xml`, `yaml`, `yml`.
 *
 * The tables come from {@see ConfigProvider}, the same source the ServiceManager-backed
 * manager is configured from, so the two cannot offer different plugin sets. Nothing here
 * names a writer: a format is added to the provider and both managers follow. That includes
 * `JavaProperties`, which has no extension and is reachable by class name only.
 *
 * @import-type WriterPluginManagerConfig from ConfigProvider
 * @internal
 */
final class StandaloneWriterPluginManager implements ContainerInterface
{
    /** @var WriterPluginManagerConfig */
    private readonly array $tables;

    public function __construct(ConfigProvider $provider = new ConfigProvider())
    {
        $this->tables = $provider->getWriterDependencies();
    }

    /**
     * @throws Exception\InvalidArgumentException
     * @throws Exception\PluginNotFoundException
     */
    #[Override]
    public function get(string $id): Writer\AbstractWriter
    {
        if (! $this->has($id)) {
            throw new Exception\PluginNotFoundException(sprintf('Config writer plugin by name %s not found', $id));
        }

        $writer = ProviderFactory::create(
            class: $this->tables['aliases'][$id] ?? $id,
            label: 'Config writer',
        );

        return $writer instanceof Writer\AbstractWriter
            ? $writer
            : throw new Exception\InvalidArgumentException(sprintf('Config writer "%s" is not a writer', $id));
    }

    #[Override]
    public function has(string $id): bool
    {
        return array_key_exists($this->tables['aliases'][$id] ?? $id, $this->tables['factories']);
    }
}
