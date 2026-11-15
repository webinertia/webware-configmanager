<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Override;
use Psr\Container\ContainerInterface;
use Webware\ConfigManager\Support\NoArgumentInstantiator;

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
        if (! $this->has($id)) {
            throw new Exception\PluginNotFoundException(sprintf(
                'Config writer plugin by name %s not found',
                $id,
            ));
        }

        /** @var class-string<Writer\AbstractWriter> $id */
        $writer = NoArgumentInstantiator::instantiate(
            class: $id,
            label: 'Config writer',
        );

        if (! $writer instanceof Writer\AbstractWriter) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s is not an instance of %s',
                $id,
                Writer\AbstractWriter::class,
            ));
        }

        return $writer;
    }

    #[Override]
    public function has(string $id): bool
    {
        return in_array($id, PluginRegistry::writerClasses(), strict: true);
    }
}
