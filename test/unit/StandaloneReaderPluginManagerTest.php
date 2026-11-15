<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader;
use Webware\ConfigManager\StandaloneReaderPluginManager;

#[CoversClass(StandaloneReaderPluginManager::class)]
final class StandaloneReaderPluginManagerTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function shortNameProvider(): array
    {
        return [
            'ini'            => ['ini'],
            'INI'            => ['INI'],
            'javaproperties' => ['javaproperties'],
        ];
    }

    /**
     * @return array<class-string<Reader\ReaderInterface>, array{class-string<Reader\ReaderInterface>}>
     */
    public static function supportedConfigClassNames(): array
    {
        return [
            Reader\Ini::class            => [Reader\Ini::class],
            Reader\Json::class           => [Reader\Json::class],
            Reader\Xml::class            => [Reader\Xml::class],
            Reader\Yaml::class           => [Reader\Yaml::class],
            Reader\JavaProperties::class => [Reader\JavaProperties::class],
        ];
    }

    /**
     * @param class-string<Reader\ReaderInterface> $class Plugin class to retrieve and expect.
     */
    #[DataProvider('supportedConfigClassNames')]
    #[Test]
    public function canRetrieveReaderByPluginClassName(string $class): void
    {
        $manager = new StandaloneReaderPluginManager();

        static::assertTrue($manager->has($class));
        static::assertInstanceOf($class, $manager->get($class));
    }

    #[Test]
    public function getThrowsExceptionIfPluginNotFound(): void
    {
        $manager = new StandaloneReaderPluginManager();

        $this->expectException(Exception\PluginNotFoundException::class);
        $this->expectExceptionMessageIs('Config reader plugin by name unknown not found');
        $manager->get('unknown');
    }

    #[Test]
    public function hasReturnsFalseForClassThatIsNotAReader(): void
    {
        static::assertFalse(new StandaloneReaderPluginManager()->has(stdClass::class));
    }

    #[Test]
    public function hasReturnsFalseForTheReaderInterface(): void
    {
        static::assertFalse(new StandaloneReaderPluginManager()->has(Reader\ReaderInterface::class));
    }

    #[DataProvider('shortNameProvider')]
    #[Test]
    public function shortNamesAreNotSupported(string $id): void
    {
        $manager = new StandaloneReaderPluginManager();

        static::assertFalse($manager->has($id));

        $this->expectException(Exception\PluginNotFoundException::class);
        $manager->get($id);
    }
}
