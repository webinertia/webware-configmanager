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
        // An extension is an alias for its class, resolved through the registry so the
        // alias map has a single home. That leaves the match below with nothing but
        // concrete classes, which is what keeps the instantiation statically known: an
        // index into a class-string array instead trips ambiguous-instantiation-target.
        $class = PluginRegistry::readerExtensions()[$id] ?? $id;

        // Each arm names one concrete class, so no analysis acknowledgement is needed.
        // The cost is that this list has to be extended alongside PluginRegistry; both
        // everyReaderInTheRegistryIsBuilt and every... test here fail if it is not.
        return match ($class) {
            Reader\Ini::class            => new Reader\Ini(),
            Reader\Json::class           => new Reader\Json(),
            Reader\Xml::class            => new Reader\Xml(),
            Reader\Yaml::class           => new Reader\Yaml(),
            Reader\JavaProperties::class => new Reader\JavaProperties(),
            default                      => throw new Exception\PluginNotFoundException(sprintf(
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
