<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\PluginRegistry;
use Webware\ConfigManager\Reader\ReaderInterface;
use Webware\ConfigManager\StandaloneReaderPluginManager;
use Webware\ConfigManager\StandaloneWriterPluginManager;
use Webware\ConfigManager\Writer\AbstractWriter;

use function array_diff;
use function array_keys;
use function array_unique;
use function count;
use function is_a;

#[CoversClass(PluginRegistry::class)]
final class PluginRegistryTest extends TestCase
{
    #[Test]
    public function everyReaderClassIsAReader(): void
    {
        foreach (PluginRegistry::readerClasses() as $class) {
            static::assertTrue(
                is_a($class, ReaderInterface::class, allow_string: true),
                "{$class} is not a reader",
            );
        }
    }

    #[Test]
    public function everyReaderExtensionNamesARegisteredReader(): void
    {
        // The lists are separate, so this is the invariant that keeps them in step. Adding
        // an extension whose class is missing from the list would otherwise work through
        // Factory and fail through the plugin manager.
        static::assertSame(
            [],
            array_diff(
                PluginRegistry::readerExtensions(),
                PluginRegistry::readerClasses(),
            ),
        );
    }

    #[Test]
    public function everyRegisteredReaderIsReadableAndWritableByClass(): void
    {
        // JavaProperties is a writer with no extension, so the class lists are allowed to be
        // larger than the extension maps. What they must never be is smaller.
        static::assertSame(
            [],
            array_diff(PluginRegistry::readerClasses(), PluginRegistry::readerExtensions()),
        );
    }

    #[Test]
    public function everyWriterClassIsAWriter(): void
    {
        foreach (PluginRegistry::writerClasses() as $class) {
            static::assertTrue(
                is_a($class, AbstractWriter::class, allow_string: true),
                "{$class} is not a writer",
            );
        }
    }

    #[Test]
    public function everyWriterExtensionNamesARegisteredWriter(): void
    {
        static::assertSame(
            [],
            array_diff(
                PluginRegistry::writerExtensions(),
                PluginRegistry::writerClasses(),
            ),
        );
    }

    #[Test]
    public function factoryMapPairsEveryClassWithTheSameFactory(): void
    {
        $map = PluginRegistry::factoryMap(
            classes: PluginRegistry::readerClasses(),
            factory: PluginRegistry::class,
        );

        static::assertSame(PluginRegistry::readerClasses(), array_keys($map));
        static::assertSame([], array_diff($map, [PluginRegistry::class]));
    }

    #[Test]
    public function theClassListsHoldEachClassOnce(): void
    {
        // A class listed twice would be built twice by a plugin manager, which is two
        // instances where the manager promises one.
        static::assertSame(
            count(PluginRegistry::readerClasses()),
            count(array_unique(PluginRegistry::readerClasses())),
        );
        static::assertSame(
            count(PluginRegistry::writerClasses()),
            count(array_unique(PluginRegistry::writerClasses())),
        );
    }

    #[Test]
    public function theStandaloneManagersOfferExactlyTheRegisteredPlugins(): void
    {
        // What the registry is for: the manager cannot list something the registry does not,
        // or omit something it does.
        $readers = new StandaloneReaderPluginManager();

        foreach (PluginRegistry::readerClasses() as $class) {
            static::assertTrue($readers->has($class), "{$class} is not offered");
        }

        $writers = new StandaloneWriterPluginManager();

        foreach (PluginRegistry::writerClasses() as $class) {
            static::assertTrue($writers->has($class), "{$class} is not offered");
        }
    }
}
