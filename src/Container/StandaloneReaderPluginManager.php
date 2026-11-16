<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Override;
use Psr\Container\ContainerInterface;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader;

use function array_key_exists;
use function in_array;
use function sprintf;

/**
 * Container for reader plugins that does not require a service manager.
 *
 * Implements PSR-11 2.x, so identifiers are typed strings. A reader is identified
 * either by its fully qualified class name or by its format extension, which is
 * registered as an alias: `ini`, `json`, `xml`, `yaml`, `yml`, `properties`.
 *
 * @internal
 */
final class StandaloneReaderPluginManager implements ContainerInterface
{
    /**
     * @throws Exception\InvalidArgumentException
     * @throws Exception\PluginNotFoundException
     */
    #[Override]
    public function get(string $id): Reader\ReaderInterface
    {
        // An identifier is either an extension or a class name, so resolve it to a class
        // first. Every arm indexes the shape with a literal key, which resolves to exactly
        // one class; indexing with $id itself is a variable index and trips
        // ambiguous-instantiation-target. `yml` needs no arm of its own because it names
        // the same class as `yaml`.
        $byExtension = PluginRegistry::readerExtensions();
        $class       = $byExtension[$id] ?? $id;

        return match ($class) {
            $byExtension['ini']        => new $byExtension['ini'](),
            $byExtension['json']       => new $byExtension['json'](),
            $byExtension['xml']        => new $byExtension['xml'](),
            $byExtension['yaml']       => new $byExtension['yaml'](),
            $byExtension['properties'] => new $byExtension['properties'](),
            default                    => throw new Exception\PluginNotFoundException(sprintf(
                'Config reader plugin by name %s not found',
                $id,
            )),
        };
    }

    #[Override]
    public function has(string $id): bool
    {
        return (
            array_key_exists($id, PluginRegistry::readerExtensions())
                || in_array($id, PluginRegistry::readerClasses(), strict: true)
        );
    }
}
