<?php

declare(strict_types=1);

namespace WebwareTestIntegration\ConfigManager;

use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\ConfigAggregator;
use Webware\ConfigManager\ConfigProvider;
use Webware\ConfigManager\Container\ReaderPluginManager;
use Webware\ConfigManager\Container\WriterPluginManager;
use Webware\ConfigManager\Factory;
use Webware\ConfigManager\Reader\Ini;
use Webware\ConfigManager\Writer\Json;

use function bin2hex;
use function file_exists;
use function getenv;
use function putenv;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

/**
 * Boots an application container whose config management is this package.
 *
 * The fixture under Fixture/application is the two files every application has: config.php
 * aggregates, container.php builds the service manager from the merged config and injects
 * it as the `config` service. That is the whole consumer contract, and it is what Mezzio,
 * laminas-mvc and anything else consuming laminas-servicemanager does too.
 *
 * The reason this is worth testing outside the unit suite: the merged config has to declare
 * the plugin managers in a form a real container can build, from a real config service,
 * rather than the tables being handed to a manager the test constructed itself.
 */
#[CoversClass(ConfigAggregator::class)]
#[CoversClass(ConfigProvider::class)]
final class ServiceManagerBootstrapTest extends TestCase
{
    private ServiceManager $container;

    private string $cacheFile;

    private string $writtenFile;

    #[Test]
    public function aWarmConfigCacheProducesTheSameConfiguration(): void
    {
        $this->boot();
        $cold = $this->config();

        // The first boot with a path writes the file; the second reads it and never runs
        // the providers, which is the aggregator's documented behaviour.
        putenv("WEBWARE_SMOKE_CONFIG_CACHE={$this->cacheFile}");
        $this->boot();
        $this->boot();
        putenv('WEBWARE_SMOKE_CONFIG_CACHE');

        static::assertFileExists($this->cacheFile);
        static::assertSame($cold, $this->config());
    }

    #[Test]
    public function factoryReadsAndWritesThroughTheContainerBuiltManagers(): void
    {
        $this->boot();

        Factory::setReaderPluginManager($this->container->get(ReaderPluginManager::class));
        Factory::setWriterPluginManager($this->container->get(WriterPluginManager::class));

        static::assertTrue(Factory::toFile($this->writtenFile, new Config(['application' => ['persisted' => true]])));

        $reloaded = Factory::fromFile(
            filename          : $this->writtenFile,
            returnConfigObject: true,
        );

        static::assertInstanceOf(Config::class, $reloaded);
        static::assertTrue($reloaded->application->persisted);
    }

    #[Test]
    public function theContainerResolvesBothPluginManagers(): void
    {
        $this->boot();

        static::assertTrue($this->container->get(ReaderPluginManager::class)->has(Ini::class));
        static::assertTrue($this->container->get(WriterPluginManager::class)->has(Json::class));
    }

    #[Test]
    public function theMergedConfigBecomesTheConfigService(): void
    {
        $this->boot();

        static::assertSame('hello from the fixture', $this->config()['application']['greeting']);
    }

    #[Test]
    public function thePluginTablesSurviveAggregationAsTopLevelKeys(): void
    {
        $this->boot();

        foreach ([ReaderPluginManager::class, WriterPluginManager::class] as $id) {
            static::assertArrayHasKey($id, $this->config());
            static::assertArrayHasKey('aliases', $this->config()[$id]);
            static::assertArrayHasKey('factories', $this->config()[$id]);
        }
    }

    protected function setUp(): void
    {
        $suffix = bin2hex(random_bytes(8));

        // Neither path may exist: the cache reads any file it finds, and `require` on an
        // empty file does not return an array.
        $this->cacheFile   = sys_get_temp_dir() . "/webware-bootstrap-cache-{$suffix}.php";
        $this->writtenFile = sys_get_temp_dir() . "/webware-bootstrap-write-{$suffix}.json";
    }

    protected function tearDown(): void
    {
        putenv('WEBWARE_SMOKE_CONFIG_CACHE');

        static::assertFalse(getenv('WEBWARE_SMOKE_CONFIG_CACHE'));

        foreach ([$this->cacheFile, $this->writtenFile] as $file) {
            if (! file_exists($file)) {
                continue;
            }

            unlink($file);
        }
    }

    private function boot(): void
    {
        /** @var ServiceManager */
        $this->container = require __DIR__ . '/Fixture/application/config/container.php';
    }

    /** @return array<array-key, mixed> */
    private function config(): array
    {
        /** @var array<array-key, mixed> */
        return $this->container->get('config');
    }
}
