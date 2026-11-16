<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Laminas\ServiceManager\AbstractSingleInstancePluginManager;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;
use Webware\ConfigManager\ConfigProvider;
use Webware\ConfigManager\Reader;

use function array_replace_recursive;

/**
 * Plugin manager for configuration readers.
 *
 * Readers are identified by their fully qualified class name or by the format extension
 * registered as an alias, so `Reader\Ini::class` and `ini` both resolve. The tables come
 * from {@see ConfigProvider}, the same source the standalone manager uses, so the two
 * cannot offer different plugin sets.
 *
 * Extends the single-instance variant so plugin validation is supplied by the base class
 * from {@see $instanceOf}; there is no validate() to implement.
 *
 * @extends AbstractSingleInstancePluginManager<Reader\ReaderInterface>
 * @import-type ServiceManagerConfiguration from ServiceManager
 * @api
 */
final class ReaderPluginManager extends AbstractSingleInstancePluginManager
{
    /** @var class-string<Reader\ReaderInterface> */
    protected string $instanceOf = Reader\ReaderInterface::class;

    /**
     * @param array<array-key, mixed> $config Tables from the factory; omitted means the shipped set.
     */
    public function __construct(ContainerInterface $creationContext, array $config = [])
    {
        /** @var ServiceManagerConfiguration $merged */
        $merged = array_replace_recursive(
            new ConfigProvider()->getReaderDependencies(),
            $config,
        );

        parent::__construct($creationContext, $merged);
    }
}
