<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webware\ConfigManager\Container\PluginRegistry;
use Webware\ConfigManager\Container\StandaloneWriterPluginManager;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Writer;

#[CoversClass(StandaloneWriterPluginManager::class)]
final class StandaloneWriterPluginManagerTest extends TestCase
{
    /**
     * @return array<string, array{string, class-string<Writer\AbstractWriter>}>
     */
    public static function extensionAliasProvider(): array
    {
        $cases = [];

        foreach (PluginRegistry::writerExtensions() as $extension => $class) {
            $cases[$extension] = [$extension, $class];
        }

        return $cases;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAliasShortNameProvider(): array
    {
        return [
            'phparray' => ['phparray'],
            'PHP'      => ['PHP'],
            'YAML'     => ['YAML'],
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
     * @param class-string<Writer\AbstractWriter> $expected
     */
    #[DataProvider('extensionAliasProvider')]
    #[Test]
    public function aFormatExtensionResolvesToItsWriter(string $extension, string $expected): void
    {
        // An extension is an alias for its writer, resolved through the registry so the
        // alias map has one home rather than being repeated in the match.
        $manager = new StandaloneWriterPluginManager();

        static::assertTrue($manager->has($extension));
        static::assertInstanceOf($expected, $manager->get($extension));
    }

    /**
     * @param class-string<Writer\AbstractWriter> $class Plugin class to retrieve and expect.
     */
    #[DataProvider('supportedConfigClassNames')]
    #[Test]
    public function canRetrieveWriterByPluginClassName(string $class): void
    {
        $manager = new StandaloneWriterPluginManager();

        static::assertTrue($manager->has($class));
        static::assertInstanceOf($class, $manager->get($class));
    }

    #[Test]
    public function everyWriterInTheRegistryIsBuilt(): void
    {
        // get() maps identifiers to concrete classes in a match, and the alias lookup runs
        // before it. This fails the moment a writer is added to PluginRegistry and either
        // is not extended, which is the only way they can drift.
        $manager = new StandaloneWriterPluginManager();

        foreach (PluginRegistry::writerClasses() as $class) {
            static::assertInstanceOf($class, $manager->get($class));
        }
    }

    #[Test]
    public function getThrowsExceptionIfPluginNotFound(): void
    {
        $manager = new StandaloneWriterPluginManager();

        $this->expectException(Exception\PluginNotFoundException::class);
        $this->expectExceptionMessageIs('Config writer plugin by name unknown not found');
        $manager->get('unknown');
    }

    #[Test]
    public function hasReturnsFalseForClassThatIsNotAWriter(): void
    {
        static::assertFalse(new StandaloneWriterPluginManager()->has(stdClass::class));
    }

    #[Test]
    public function hasReturnsFalseForTheAbstractWriter(): void
    {
        static::assertFalse(new StandaloneWriterPluginManager()->has(Writer\AbstractWriter::class));
    }

    #[DataProvider('nonAliasShortNameProvider')]
    #[Test]
    public function shortNamesThatAreNotAliasesAreNotSupported(string $id): void
    {
        // Aliases are the lowercase format extensions and nothing else: matching is exact,
        // so a differently cased or differently spelled short name is still rejected.
        $manager = new StandaloneWriterPluginManager();

        static::assertFalse($manager->has($id));

        $this->expectException(Exception\PluginNotFoundException::class);
        $manager->get($id);
    }
}
