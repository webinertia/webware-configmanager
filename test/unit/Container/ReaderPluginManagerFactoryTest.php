<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Container;

use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Container\ReaderPluginManager;
use Webware\ConfigManager\Container\ReaderPluginManagerFactory;
use Webware\ConfigManager\Reader;

#[CoversClass(ReaderPluginManagerFactory::class)]
final class ReaderPluginManagerFactoryTest extends TestCase
{
    #[Test]
    public function itBuildsAManagerThatResolvesEveryBundledReader(): void
    {
        $container = new ServiceManager();
        $manager   = new ReaderPluginManagerFactory()($container);

        static::assertInstanceOf(ReaderPluginManager::class, $manager);

        foreach ([
            Reader\Ini::class,
            Reader\Json::class,
            Reader\Xml::class,
            Reader\Yaml::class,
            Reader\JavaProperties::class,
        ] as $reader) {
            static::assertInstanceOf($reader, $manager->get($reader));
        }
    }
}
