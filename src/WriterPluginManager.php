<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Laminas\ServiceManager\AbstractSingleInstancePluginManager;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Psr\Container\ContainerInterface;

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
 */
class WriterPluginManager extends AbstractSingleInstancePluginManager
{
    /** @var class-string<Writer\AbstractWriter> */
    protected string $instanceOf = Writer\AbstractWriter::class;

    /** @var array<class-string<Writer\AbstractWriter>, class-string<InvokableFactory>> */
    private const array FACTORIES = [
        Writer\Ini::class            => InvokableFactory::class,
        Writer\JavaProperties::class => InvokableFactory::class,
        Writer\Json::class           => InvokableFactory::class,
        Writer\PhpArray::class       => InvokableFactory::class,
        Writer\Xml::class            => InvokableFactory::class,
        Writer\Yaml::class           => InvokableFactory::class,
    ];

    public function __construct(ContainerInterface $creationContext, array $config = [])
    {
        /** @var ServiceManagerConfiguration $merged */
        $merged = array_replace_recursive(['factories' => self::FACTORIES], $config);

        parent::__construct($creationContext, $merged);
    }
}
