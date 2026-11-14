<?php

declare(strict_types=1);

namespace WebwareTestIntegration\ConfigManager;

use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Factory;
use Webware\ConfigManager\ReaderPluginManager;
use Webware\ConfigManager\WriterPluginManager;

use function file_exists;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(Factory::class)]
final class ConfigFactoryIntegrationTest extends TestCase
{
    private string $tmpFile;

    #[Test]
    public function customPluginManagersReplaceTheStandaloneDefaults(): void
    {
        $container = new ServiceManager();

        Factory::setReaderPluginManager(new ReaderPluginManager($container));
        Factory::setWriterPluginManager(new WriterPluginManager($container));

        $config = Factory::fromFile(__DIR__ . '/../unit/TestAssets/Ini/include-base.ini', true);

        static::assertInstanceOf(Config::class, $config);
        static::assertNotEmpty($config->toArray());
    }

    #[Test]
    public function fromFilesMergesEverySupportedFormatIntoOneConfig(): void
    {
        $files = [
            __DIR__ . '/../unit/TestAssets/Ini/include-base.ini',
            __DIR__ . '/../unit/TestAssets/Php/include-base.php',
        ];

        $config = Factory::fromFiles($files, true);

        static::assertInstanceOf(Config::class, $config);
        static::assertSame('bar', $config->base->foo);
        static::assertSame('baz', $config->test->bar);
    }

    #[Test]
    public function toFileAndFromFileRoundTripThroughThePluginManagers(): void
    {
        $config = new Config(['webware' => ['configmanager' => ['enabled' => true]]]);

        static::assertTrue(Factory::toFile($this->tmpFile, $config));

        $result = Factory::fromFile($this->tmpFile, true);

        static::assertInstanceOf(Config::class, $result);
        static::assertTrue($result->webware->configmanager->enabled);
    }

    protected function setUp(): void
    {
        $this->tmpFile =
            tempnam(
                directory: sys_get_temp_dir(),
                prefix   : 'webware-configmanager',
            ) . '.json';
        file_put_contents(
            filename: $this->tmpFile,
            data    : '{}',
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }
}
