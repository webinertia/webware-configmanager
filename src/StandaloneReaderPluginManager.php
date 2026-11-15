<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Override;
use Psr\Container\ContainerInterface;

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
    /** @var list<class-string<Reader\ReaderInterface>> */
    private const array PLUGINS = [
        Reader\Ini::class,
        Reader\Json::class,
        Reader\Xml::class,
        Reader\Yaml::class,
        Reader\JavaProperties::class,
    ];

    /**
     * @throws Exception\InvalidArgumentException
     * @throws Exception\PluginNotFoundException
     */
    #[Override]
    public function get(string $id): Reader\ReaderInterface
    {
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
        return in_array($id, self::PLUGINS, strict: true);
    }
}
