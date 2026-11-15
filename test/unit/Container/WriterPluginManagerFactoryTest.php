<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Container;

use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Container\WriterPluginManagerFactory;
use Webware\ConfigManager\Writer;
use Webware\ConfigManager\WriterPluginManager;

#[CoversClass(WriterPluginManagerFactory::class)]
final class WriterPluginManagerFactoryTest extends TestCase
{
    #[Test]
    public function itBuildsAManagerThatResolvesEveryBundledWriter(): void
    {
        $container = new ServiceManager();
        $manager   = new WriterPluginManagerFactory()($container);

        static::assertInstanceOf(WriterPluginManager::class, $manager);

        foreach ([
            Writer\Ini::class,
            Writer\JavaProperties::class,
            Writer\Json::class,
            Writer\PhpArray::class,
            Writer\Xml::class,
            Writer\Yaml::class,
        ] as $writer) {
            static::assertInstanceOf($writer, $manager->get($writer));
        }
    }
}
