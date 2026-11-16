<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Override;
use Psr\Container\ContainerInterface;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Writer;

use function array_key_exists;
use function in_array;
use function sprintf;

/**
 * Container for writer plugins that does not require a service manager.
 *
 * Implements PSR-11 2.x, so identifiers are typed strings. A writer is identified
 * either by its fully qualified class name or by its format extension, which is
 * registered as an alias: `php`, `ini`, `json`, `xml`, `yaml`, `yml`.
 *
 * @internal
 */
final class StandaloneWriterPluginManager implements ContainerInterface
{
    /**
     * @throws Exception\InvalidArgumentException
     * @throws Exception\PluginNotFoundException
     */
    #[Override]
    public function get(string $id): Writer\AbstractWriter
    {
        // See StandaloneReaderPluginManager::get(). JavaProperties needs its own arm
        // because it is a registered writer with no extension, so the map cannot reach it.
        $byExtension = PluginRegistry::writerExtensions();
        $class       = $byExtension[$id] ?? $id;

        return match ($class) {
            $byExtension['php']          => new $byExtension['php'](),
            $byExtension['ini']          => new $byExtension['ini'](),
            $byExtension['json']         => new $byExtension['json'](),
            $byExtension['xml']          => new $byExtension['xml'](),
            $byExtension['yaml']         => new $byExtension['yaml'](),
            Writer\JavaProperties::class => new Writer\JavaProperties(),
            default                      => throw new Exception\PluginNotFoundException(sprintf(
                'Config writer plugin by name %s not found',
                $id,
            )),
        };
    }

    #[Override]
    public function has(string $id): bool
    {
        return (
            array_key_exists($id, PluginRegistry::writerExtensions())
                || in_array($id, PluginRegistry::writerClasses(), strict: true)
        );
    }
}
