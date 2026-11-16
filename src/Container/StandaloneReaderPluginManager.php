<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Override;
use Psr\Container\ContainerInterface;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader;

use function in_array;
use function sprintf;

/**
 * Container for reader plugins that does not require a service manager.
 *
 * Implements PSR-11 2.x, so identifiers are typed strings. Readers are
 * identified by their fully qualified class name; no aliases are registered.
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
        // Each arm names one concrete class, so the instantiation is statically known and
        // needs no analysis acknowledgement. Indexing a class-string array would not: mago
        // reports `ambiguous-instantiation-target` for the variable, which is why this is a
        // match over the identifiers rather than a lookup.
        //
        // The cost is that this map duplicates the one in PluginRegistry::readerExtensions().
        // PluginRegistryTest asserts the two agree, and both have to be edited to add a
        // reader, which is the price of removing the acknowledgement.
        return match ($id) {
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
        return in_array($id, PluginRegistry::readerClasses(), strict: true);
    }
}
