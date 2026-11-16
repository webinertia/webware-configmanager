<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webware\ConfigManager\ConfigProvider;
use Webware\ConfigManager\Container\StandaloneReaderPluginManager;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader;

use function array_keys;

#[CoversClass(StandaloneReaderPluginManager::class)]
final class StandaloneReaderPluginManagerTest extends TestCase
{
    /**
     * @return array<string, array{string, class-string<Reader\ReaderInterface>}>
     */
    public static function extensionAliasProvider(): array
    {
        $cases = [];

        foreach (new ConfigProvider()->getReaderDependencies()['aliases'] as $extension => $class) {
            $cases[$extension] = [$extension, $class];
        }

        return $cases;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function shortNameProvider(): array
    {
        return [
            'INI'            => ['INI'],
            'Json'           => ['Json'],
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
     * @param class-string<Reader\ReaderInterface> $expected
     */
    #[DataProvider('extensionAliasProvider')]
    #[Test]
    public function aFormatExtensionResolvesToItsReader(string $extension, string $expected): void
    {
        // An extension is an alias for its reader, resolved through the registry so the
        // alias map has one home rather than being repeated in the match below.
        $manager = new StandaloneReaderPluginManager();

        static::assertTrue($manager->has($extension));
        static::assertInstanceOf($expected, $manager->get($extension));
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
    public function everyReaderInTheProviderIsBuilt(): void
    {
        // There is no second list to keep: the manager reads the factories table, so this
        // guards that every registered class is buildable through the mapped factory.
        $manager = new StandaloneReaderPluginManager();

        foreach (array_keys(new ConfigProvider()->getReaderDependencies()['factories']) as $class) {
            static::assertInstanceOf($class, $manager->get($class));
        }
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
    public function shortNamesThatAreNotAliasesAreNotSupported(string $id): void
    {
        // Aliases are the lowercase format extensions and nothing else: matching is exact,
        // so a differently cased or differently spelled short name is still rejected.
        $manager = new StandaloneReaderPluginManager();

        static::assertFalse($manager->has($id));

        $this->expectException(Exception\PluginNotFoundException::class);
        $manager->get($id);
    }
}
