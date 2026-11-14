<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\StandaloneWriterPluginManager;
use Webware\ConfigManager\Writer;

#[CoversClass(StandaloneWriterPluginManager::class)]
final class StandaloneWriterPluginManagerTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function shortNameProvider(): array
    {
        return [
            'php'      => ['php'],
            'phparray' => ['phparray'],
            'yaml'     => ['yaml'],
        ];
    }

    /**
     * @return array<class-string<Writer\AbstractWriter>, array{class-string<Writer\AbstractWriter>}>
     */
    public static function supportedConfigClassNames(): array
    {
        return [
            Writer\Ini::class            => [Writer\Ini::class],
            Writer\JavaProperties::class => [Writer\JavaProperties::class],
            Writer\Json::class           => [Writer\Json::class],
            Writer\PhpArray::class       => [Writer\PhpArray::class],
            Writer\Xml::class            => [Writer\Xml::class],
            Writer\Yaml::class           => [Writer\Yaml::class],
        ];
    }

    /**
     * @param class-string<Writer\AbstractWriter> $class Plugin class to retrieve and expect.
     */
    #[DataProvider('supportedConfigClassNames')]
    #[Test]
    public function testCanRetrieveWriterByPluginClassName(string $class): void
    {
        $manager = new StandaloneWriterPluginManager();

        static::assertTrue($manager->has($class));
        static::assertInstanceOf($class, $manager->get($class));
    }

    #[Test]
    public function testGetThrowsExceptionIfPluginNotFound(): void
    {
        $manager = new StandaloneWriterPluginManager();

        $this->expectException(Exception\PluginNotFoundException::class);
        $this->expectExceptionMessage('Config writer plugin by name unknown not found');
        $manager->get('unknown');
    }

    #[Test]
    public function testHasReturnsFalseForClassThatIsNotAWriter(): void
    {
        static::assertFalse(new StandaloneWriterPluginManager()->has(stdClass::class));
    }

    #[Test]
    public function testHasReturnsFalseForTheAbstractWriter(): void
    {
        static::assertFalse(new StandaloneWriterPluginManager()->has(Writer\AbstractWriter::class));
    }

    #[DataProvider('shortNameProvider')]
    #[Test]
    public function testShortNamesAreNotSupported(string $id): void
    {
        $manager = new StandaloneWriterPluginManager();

        static::assertFalse($manager->has($id));

        $this->expectException(Exception\PluginNotFoundException::class);
        $manager->get($id);
    }
}
