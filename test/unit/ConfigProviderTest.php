<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\ConfigProvider;
use Webware\ConfigManager\Container\ReaderPluginManagerFactory;
use Webware\ConfigManager\Container\WriterPluginManagerFactory;
use Webware\ConfigManager\ReaderPluginManager;
use Webware\ConfigManager\WriterPluginManager;

#[CoversClass(ConfigProvider::class)]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function dependenciesRegisterTheServiceManagerBackedPluginManagers(): void
    {
        $expected = [
            'abstract_factories' => [],
            'factories'          => [
                ReaderPluginManager::class => ReaderPluginManagerFactory::class,
                WriterPluginManager::class => WriterPluginManagerFactory::class,
            ],
        ];

        static::assertSame($expected, new ConfigProvider()->getDependencies());
    }

    #[Test]
    public function theAbstractConfigFactoryIsNotRegisteredByDefault(): void
    {
        // The entry is present but commented out. Registering an abstract factory makes
        // it run for every name without an explicit factory, so it stays an opt-in that
        // a consumer enables deliberately.
        static::assertSame([], new ConfigProvider()->getDependencies()['abstract_factories']);
    }

    #[Test]
    public function theInvocationWrapsTheDependenciesUnderTheirKey(): void
    {
        $provider = new ConfigProvider();

        static::assertSame(
            ['dependencies' => $provider->getDependencies()],
            $provider(),
        );
    }
}
