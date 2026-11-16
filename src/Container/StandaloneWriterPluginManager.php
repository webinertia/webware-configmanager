<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Override;
use Psr\Container\ContainerInterface;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Writer;

use function in_array;
use function sprintf;

/**
 * Container for writer plugins that does not require a service manager.
 *
 * Implements PSR-11 2.x, so identifiers are typed strings. Writers are
 * identified by their fully qualified class name; no aliases are registered.
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
        // Each arm names one concrete class, so the instantiation is statically known and
        // needs no analysis acknowledgement. See StandaloneReaderPluginManager::get() for
        // why this is a match over identifiers rather than a lookup in a class-string array,
        // and for the duplication this accepts.
        return match ($id) {
            Writer\Ini::class            => new Writer\Ini(),
            Writer\JavaProperties::class => new Writer\JavaProperties(),
            Writer\Json::class           => new Writer\Json(),
            Writer\PhpArray::class       => new Writer\PhpArray(),
            Writer\Xml::class            => new Writer\Xml(),
            Writer\Yaml::class           => new Writer\Yaml(),
            default                      => throw new Exception\PluginNotFoundException(sprintf(
                'Config writer plugin by name %s not found',
                $id,
            )),
        };
    }

    #[Override]
    public function has(string $id): bool
    {
        return in_array($id, PluginRegistry::writerClasses(), strict: true);
    }
}
