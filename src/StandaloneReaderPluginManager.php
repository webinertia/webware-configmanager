<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Override;
use Psr\Container\ContainerInterface;
use Webware\ConfigManager\Support\NoArgumentInstantiator;

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
        if (! $this->has($id)) {
            throw new Exception\PluginNotFoundException(sprintf(
                'Config reader plugin by name %s not found',
                $id,
            ));
        }

        /**
         * The registry lists only readers, and PluginRegistryTest asserts it, so the built
         * instance is a reader. The alternative was an instanceof check that could never
         * fail, which is a branch no test can reach.
         *
         * @var class-string<Reader\ReaderInterface> $id
         * @var Reader\ReaderInterface
         */
        return NoArgumentInstantiator::instantiate(
            class: $id,
            label: 'Config reader',
        );
    }

    #[Override]
    public function has(string $id): bool
    {
        return in_array($id, PluginRegistry::readerClasses(), strict: true);
    }
}
