<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Webware\ConfigManager\Reader;
use Webware\ConfigManager\Writer;

/**
 * The plugins this package ships, named once.
 *
 * A reader or writer is described in three places: the extension that selects it, the set
 * a plugin manager may build, and the factory that builds it. Those lists were kept apart,
 * so adding a format meant four edits and forgetting one failed only when that format was
 * used. The lists live here and the callers derive from them.
 *
 * The extension maps and the class lists are both stated rather than derived from each
 * other, because they are not the same set: `JavaProperties` is a registered writer with no
 * extension, and two extensions name `Yaml`. Both maps are asserted to name only classes
 * from their list, so a format cannot be added to one and missed in the other.
 *
 * Nothing here references the service manager, which is a development dependency. Deriving
 * the plugin manager factories is the caller's job, because doing it here would make this
 * class unloadable for a consumer that never installs the service manager.
 *
 * The shapes below name every key and pin each key to the one concrete class it selects. A
 * looser `array<string, class-string<ReaderInterface>>` would permit the interface itself,
 * which is exactly what an instantiation cannot accept, so the shape is what makes the
 * mapping reviewable at a glance.
 *
 * @type ReaderAliases = array{
 *     ini: class-string<Reader\Ini>,
 *     json: class-string<Reader\Json>,
 *     xml: class-string<Reader\Xml>,
 *     yaml: class-string<Reader\Yaml>,
 *     yml: class-string<Reader\Yaml>,
 *     properties: class-string<Reader\JavaProperties>,
 * }
 * @type ReaderClasses = array{
 *     class-string<Reader\Ini>,
 *     class-string<Reader\Json>,
 *     class-string<Reader\Xml>,
 *     class-string<Reader\Yaml>,
 *     class-string<Reader\JavaProperties>,
 * }
 * @type WriterAliases = array{
 *     php: class-string<Writer\PhpArray>,
 *     ini: class-string<Writer\Ini>,
 *     json: class-string<Writer\Json>,
 *     xml: class-string<Writer\Xml>,
 *     yaml: class-string<Writer\Yaml>,
 *     yml: class-string<Writer\Yaml>,
 * }
 * @type WriterClasses = array{
 *     class-string<Writer\Ini>,
 *     class-string<Writer\JavaProperties>,
 *     class-string<Writer\Json>,
 *     class-string<Writer\PhpArray>,
 *     class-string<Writer\Xml>,
 *     class-string<Writer\Yaml>,
 * }
 *
 * @internal
 */
final class PluginRegistry
{
    /** @var ReaderClasses */
    private const array READER_CLASSES = [
        Reader\Ini::class,
        Reader\Json::class,
        Reader\Xml::class,
        Reader\Yaml::class,
        Reader\JavaProperties::class,
    ];

    /** @var WriterClasses */
    private const array WRITER_CLASSES = [
        Writer\Ini::class,
        Writer\JavaProperties::class,
        Writer\Json::class,
        Writer\PhpArray::class,
        Writer\Xml::class,
        Writer\Yaml::class,
    ];

    /** @var ReaderAliases */
    private const array READER_EXTENSIONS = [
        'ini'        => Reader\Ini::class,
        'json'       => Reader\Json::class,
        'xml'        => Reader\Xml::class,
        'yaml'       => Reader\Yaml::class,
        'yml'        => Reader\Yaml::class,
        'properties' => Reader\JavaProperties::class,
    ];

    /** @var WriterAliases */
    private const array WRITER_EXTENSIONS = [
        'php'  => Writer\PhpArray::class,
        'ini'  => Writer\Ini::class,
        'json' => Writer\Json::class,
        'xml'  => Writer\Xml::class,
        'yaml' => Writer\Yaml::class,
        'yml'  => Writer\Yaml::class,
    ];

    /**
     * Map every class in a list to the same factory class.
     *
     * Kept here because both plugin managers build this shape, and kept out of the
     * constants because a constant cannot call a method.
     *
     * @param list<class-string> $classes
     * @param class-string $factory
     * @return array<class-string, class-string>
     */
    public static function factoryMap(array $classes, string $factory): array
    {
        $factories = [];

        foreach ($classes as $class) {
            $factories[$class] = $factory;
        }

        return $factories;
    }

    /** @return ReaderClasses */
    public static function readerClasses(): array
    {
        return self::READER_CLASSES;
    }

    /** @return ReaderAliases */
    public static function readerExtensions(): array
    {
        return self::READER_EXTENSIONS;
    }

    /** @return WriterClasses */
    public static function writerClasses(): array
    {
        return self::WRITER_CLASSES;
    }

    /** @return WriterAliases */
    public static function writerExtensions(): array
    {
        return self::WRITER_EXTENSIONS;
    }
}
