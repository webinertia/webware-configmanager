<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Laminas\Stdlib\ArrayUtils;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function file_exists;
use function get_include_path;
use function gettype;
use function is_a;
use function is_array;
use function is_file;
use function is_object;
use function is_readable;
use function is_string;
use function pathinfo;
use function sprintf;
use function stream_resolve_include_path;
use function strrchr;
use function strtolower;
use function substr;

class Factory
{
    /**
     * Plugin manager for loading readers
     *
     * @var null|ContainerInterface
     */
    public static $readers;

    /**
     * Plugin manager for loading writers
     *
     * @var null|ContainerInterface
     */
    public static $writers;

    /**
     * Registered config file extensions.
     * key is extension, value is reader instance or reader class name
     *
     * @var array<string, class-string<Reader\ReaderInterface>|Reader\ReaderInterface>
     */
    protected static $extensions = [
        'ini'        => Reader\Ini::class,
        'json'       => Reader\Json::class,
        'xml'        => Reader\Xml::class,
        'yaml'       => Reader\Yaml::class,
        'yml'        => Reader\Yaml::class,
        'properties' => Reader\JavaProperties::class,
    ];

    /**
     * Register config file extensions for writing
     * key is extension, value is writer instance or writer class name
     *
     * @var array<string, class-string<Writer\AbstractWriter>|Writer\AbstractWriter>
     */
    protected static $writerExtensions = [
        'php'  => Writer\PhpArray::class,
        'ini'  => Writer\Ini::class,
        'json' => Writer\Json::class,
        'xml'  => Writer\Xml::class,
        'yaml' => Writer\Yaml::class,
        'yml'  => Writer\Yaml::class,
    ];

    /**
     * Read a config from a file.
     *
     * @param  string  $filename
     * @param  bool $returnConfigObject
     * @param  bool $useIncludePath
     * @return array|Config
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    public static function fromFile($filename, $returnConfigObject = false, $useIncludePath = false)
    {
        $filepath = $filename;
        if (! file_exists($filename)) {
            if (! $useIncludePath) {
                throw new Exception\RuntimeException(sprintf(
                    'Filename "%s" cannot be found relative to the working directory',
                    $filename,
                ));
            }

            $fromIncludePath = stream_resolve_include_path($filename);
            if (! $fromIncludePath) {
                throw new Exception\RuntimeException(sprintf(
                    'Filename "%s" cannot be found relative to the working directory or the include_path ("%s")',
                    $filename,
                    get_include_path(),
                ));
            }
            $filepath = $fromIncludePath;
        }

        $pathinfo = pathinfo($filepath);

        if (! array_key_exists('extension', $pathinfo)) {
            throw new Exception\RuntimeException(sprintf(
                'Filename "%s" is missing an extension and cannot be auto-detected',
                $filename,
            ));
        }

        $extension = strtolower($pathinfo['extension']);

        if ('php' === $extension) {
            if (! is_file($filepath) || ! is_readable($filepath)) {
                throw new Exception\RuntimeException(sprintf(
                    "File '%s' doesn't exist or not readable",
                    $filename,
                ));
            }

            $config = include $filepath;

            return $returnConfigObject ? new Config($config) : $config;
        }

        if (array_key_exists($extension, static::$extensions)) {
            $reader = static::$extensions[$extension];
            if (! $reader instanceof Reader\ReaderInterface) {
                $reader                         = static::getReaderPluginManager()->get($reader);
                static::$extensions[$extension] = $reader;
            }

            /** @var Reader\ReaderInterface $reader */
            $config = $reader->fromFile($filepath);

            return $returnConfigObject ? new Config($config) : $config;
        }

        throw new Exception\RuntimeException(sprintf(
            'Unsupported config file extension: .%s',
            $pathinfo['extension'],
        ));
    }

    /**
     * Read configuration from multiple files and merge them.
     *
     * @param  bool $returnConfigObject
     * @param  bool $useIncludePath
     * @return array|Config
     */
    public static function fromFiles(array $files, $returnConfigObject = false, $useIncludePath = false)
    {
        $config = [];

        foreach ($files as $file) {
            $config = ArrayUtils::merge($config, static::fromFile($file, false, $useIncludePath));
        }

        return $returnConfigObject ? new Config($config) : $config;
    }

    /**
     * Get the reader plugin manager.
     *
     * If none is available, registers and returns a
     * StandaloneReaderPluginManager instance by default.
     *
     * @return ContainerInterface
     */
    public static function getReaderPluginManager()
    {
        if (null === static::$readers) {
            static::$readers = new StandaloneReaderPluginManager();
        }
        return static::$readers;
    }

    /**
     * Get the writer plugin manager.
     *
     * If none is available, registers and returns a
     * StandaloneWriterPluginManager instance by default.
     *
     * @return ContainerInterface
     */
    public static function getWriterPluginManager()
    {
        if (null === static::$writers) {
            static::$writers = new StandaloneWriterPluginManager();
        }

        return static::$writers;
    }

    /**
     * Set config reader for file extension
     *
     * @param  string $extension
     * @param  class-string<Reader\ReaderInterface>|Reader\ReaderInterface $reader
     * @throws Exception\InvalidArgumentException
     * @return void
     */
    public static function registerReader($extension, $reader)
    {
        $extension = strtolower($extension);

        static::$extensions[$extension] = self::assertPlugin($reader, Reader\ReaderInterface::class, 'Reader');
    }

    /**
     * Set config writer for file extension
     *
     * @param  string $extension
     * @param  class-string<Writer\AbstractWriter>|Writer\AbstractWriter $writer
     * @throws Exception\InvalidArgumentException
     * @return void
     */
    public static function registerWriter($extension, $writer)
    {
        $extension = strtolower($extension);

        static::$writerExtensions[$extension] = self::assertPlugin($writer, Writer\AbstractWriter::class, 'Writer');
    }

    /**
     * Set reader plugin manager
     *
     * @return void
     */
    public static function setReaderPluginManager(ContainerInterface $readers)
    {
        static::$readers = $readers;
    }

    /**
     * Set writer plugin manager
     *
     * @return void
     */
    public static function setWriterPluginManager(ContainerInterface $writers)
    {
        static::$writers = $writers;
    }

    /**
     * Writes a config to a file
     *
     * @param string $filename
     * @param array|Config $config
     * @return bool TRUE on success | FALSE on failure
     * @throws Exception\RuntimeException
     * @throws Exception\InvalidArgumentException
     */
    public static function toFile($filename, $config)
    {
        if (
            (
                is_object($config)
                && ! $config instanceof Config
            )
            || (
                ! is_object($config)
                && ! is_array($config)
            )
        ) {
            throw new Exception\InvalidArgumentException(
                __METHOD__ . " \$config should be an array or instance of Webware\\ConfigManager\\Config",
            );
        }

        $extension = substr(strrchr($filename, needle: '.'), offset: 1);

        if (! array_key_exists($extension, static::$writerExtensions)) {
            throw new Exception\RuntimeException(
                "Unsupported config file extension: '.{$extension}' for writing.",
            );
        }

        $writer = static::$writerExtensions[$extension];
        if (! $writer instanceof Writer\AbstractWriter) {
            $resolved = self::getWriterPluginManager()->get($writer);

            if (! $resolved instanceof Writer\AbstractWriter) {
                throw new Exception\RuntimeException(sprintf(
                    "Writer plugin for extension '.%s' is not an instance of %s",
                    $extension,
                    Writer\AbstractWriter::class,
                ));
            }

            $writer                               = $resolved;
            static::$writerExtensions[$extension] = $resolved;
        }

        if (is_object($config)) {
            $config = $config->toArray();
        }

        // The writer owns the write. It replaces the file atomically, and reports
        // a missing or unwritable directory through its own exception, so the
        // pre-flight checks this method used to perform are gone: they could only
        // pass and then be invalidated before the write happened.
        $writer->toFile($filename, $config);

        return true;
    }

    /**
     * Accept only an instance of the given type, or the class name of a class of that type.
     *
     * @param class-string $type
     * @throws Exception\InvalidArgumentException
     */
    private static function assertPlugin(mixed $plugin, string $type, string $label): string|object
    {
        if (is_object($plugin) && $plugin instanceof $type) {
            return $plugin;
        }

        if (! is_string($plugin) || ! is_a($plugin, $type, allow_string: true)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s should be a class-string of %s or an instance of it; received "%s"',
                $label,
                $type,
                is_object($plugin) ? $plugin::class : gettype($plugin),
            ));
        }

        return $plugin;
    }
}
