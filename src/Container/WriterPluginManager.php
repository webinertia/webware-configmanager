<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Laminas\ServiceManager\AbstractSingleInstancePluginManager;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;
use Webware\ConfigManager\Writer;

use function array_replace_recursive;

/**
 * Plugin manager for configuration writers.
 *
 * Writers are identified by their fully qualified class name; no aliases are
 * registered. Extends the single-instance variant so plugin validation is
 * supplied by the base class from {@see $instanceOf}; there is no validate()
 * to implement.
 *
 * @extends AbstractSingleInstancePluginManager<Writer\AbstractWriter>
 * @import-type ServiceManagerConfiguration from ServiceManager
 * @api
 */
final class WriterPluginManager extends AbstractSingleInstancePluginManager
{
    /** @var class-string<Writer\AbstractWriter> */
    protected string $instanceOf = Writer\AbstractWriter::class;

    /**
     * @param array<array-key, mixed> $config
     */
    public function __construct(ContainerInterface $creationContext, array $config = [])
    {
        /** @var ServiceManagerConfiguration $merged */
        $merged = array_replace_recursive(
            ['factories' => PluginRegistry::factoryMap(PluginRegistry::writerClasses(), InvokableFactory::class)],
            $config,
        );

        parent::__construct($creationContext, $merged);
    }
}
