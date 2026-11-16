<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Psr\Container\ContainerInterface;
use Webware\ConfigManager\Merge\ConfigMerger;
use Webware\ConfigManager\Provider\ConfigProviderResolver;
use Webware\FileWriter\Exception as WriterException;

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

/**
 * Reading, writing and plugin registration are separate responsibilities that share the
 * static reader and writer tables, so they stay together rather than being split into
 * collaborators that would each need the same registry.
 *
 * @mago-expect lint:too-many-methods
 * @api
 */
final class Factory
{
    /**
     * Plugin manager for loading readers
     *
     * @var null|ContainerInterface
     */
    public static ?ContainerInterface $readers = null;

    /**
     * Plugin manager for loading writers
     *
     * @var null|ContainerInterface
     */
    public static ?ContainerInterface $writers = null;

    /**
     * Registered config file extensions.
     * key is extension, value is reader instance or reader class name
     *
     * Starts as the shipped set and is replaced as readers are resolved, so it stays a
     * cache of what this process will use. {@see PluginRegistry} owns the shipped set.
     *
     * @var null|array<string, class-string<Reader\ReaderInterface>|Reader\ReaderInterface>
     */
    private static ?array $extensions = null;

    /**
     * Register config file extensions for writing
     * key is extension, value is writer instance or writer class name
     *
     * @var null|array<string, class-string<Writer\AbstractWriter>|Writer\AbstractWriter>
     */
    private static ?array $writerExtensions = null;

    /**
     * Read a config from a file.
     *
     * @return ($returnConfigObject is true ? Config : array<array-key, mixed>)
     * @throws Exception\InvalidArgumentException
     * The two flags are the public signature inherited from laminas-config, and both
     * callers rely on them positionally.
     *
     * @throws Exception\RuntimeException
     * @mago-expect lint:no-boolean-flag-parameter
     */
    public static function fromFile(
        string $filename,
        bool $returnConfigObject = false,
        bool $useIncludePath = false,
    ): array|Config {
        $filepath = self::existingOrIncludePath($filename);

        if (null === $filepath || (! $useIncludePath && ! file_exists($filename))) {
            throw new Exception\RuntimeException(sprintf(
                $useIncludePath
                    ? 'Filename "%s" cannot be found relative to the working directory or the include_path ("%s")'
                    : 'Filename "%s" cannot be found relative to the working directory',
                $filename,
                get_include_path(),
            ));
        }

        $pathinfo = pathinfo($filepath);

        if (! array_key_exists('extension', $pathinfo)) {
            throw new Exception\RuntimeException(sprintf(
                'Filename "%s" is missing an extension and cannot be auto-detected',
                $filename,
            ));
        }

        $extension = strtolower($pathinfo['extension']);

        $config = self::readConfig($extension, $filepath, $filename);

        return $returnConfigObject ? new Config($config) : $config;
    }

    /**
     * Read configuration from multiple files and merge them.
     *
     * @return ($returnConfigObject is true ? Config : array<array-key, mixed>)
     * @mago-expect lint:no-boolean-flag-parameter
     */
    public static function fromFiles(
        array $files,
        bool $returnConfigObject = false,
        bool $useIncludePath = false,
    ): array|Config {
        $config = [];

        foreach ($files as $file) {
            $config = ConfigMerger::merge(
                $config,
                static::fromFile(
                    filename          : $file,
                    returnConfigObject: false,
                    useIncludePath    : $useIncludePath,
                ),
            );
        }

        return $returnConfigObject ? new Config($config) : $config;
    }

    /**
     * Read configuration from a set of config providers and merge them.
     *
     * The provider contract is the laminas one: a class-string is instantiated with no
     * arguments and must then be callable, and a callable is used verbatim. Every
     * provider must return an array, or stream arrays, and the arrays are merged in the
     * order given, so a later provider wins. A provider listed twice is an error.
     *
     * This is the replacement for `Laminas\ConfigAggregator\ConfigAggregator`, so a
     * provider list written for that package is used unchanged:
     *
     * ```php
     * $config = Factory::fromProviders([
     *     \Mezzio\ConfigProvider::class,
     *     new ArrayProvider(['config_cache_enabled' => false]),
     *     new PhpFileProvider(__DIR__ . '/autoload/{{,*.}global,{,*.}local}.php'),
     * ]);
     * ```
     *
     * @param iterable<class-string|callable> $providers
     * @return ($returnConfigObject is true ? Config : array<array-key, mixed>)
     * @throws Exception\InvalidArgumentException
     * @mago-expect lint:no-boolean-flag-parameter
     */
    public static function fromProviders(iterable $providers, bool $returnConfigObject = false): array|Config
    {
        $config = new ConfigProviderResolver()->aggregate($providers);

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
    public static function getReaderPluginManager(): ContainerInterface
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
    public static function getWriterPluginManager(): ContainerInterface
    {
        if (null === static::$writers) {
            static::$writers = new StandaloneWriterPluginManager();
        }

        return static::$writers;
    }

    /**
     * Set config reader for file extension
     *
     * @param  class-string<Reader\ReaderInterface>|Reader\ReaderInterface $reader
     * @throws Exception\InvalidArgumentException
     */
    public static function registerReader(string $extension, mixed $reader): void
    {
        $extension = strtolower($extension);
        $plugin    = self::assertPlugin(
            plugin: $reader,
            type  : Reader\ReaderInterface::class,
            label : 'Reader',
        );

        $extensions             = self::extensions();
        $extensions[$extension] = $plugin;
        self::$extensions       = $extensions;
    }

    /**
     * Set config writer for file extension
     *
     * @param  class-string<Writer\AbstractWriter>|Writer\AbstractWriter $writer
     * @throws Exception\InvalidArgumentException
     */
    public static function registerWriter(string $extension, mixed $writer): void
    {
        $extension = strtolower($extension);
        $plugin    = self::assertPlugin(
            plugin: $writer,
            type  : Writer\AbstractWriter::class,
            label : 'Writer',
        );

        $extensions             = self::writerExtensions();
        $extensions[$extension] = $plugin;
        self::$writerExtensions = $extensions;
    }

    /**
     * Set reader plugin manager
     *
     */
    public static function setReaderPluginManager(ContainerInterface $readers): void
    {
        static::$readers = $readers;
    }

    /**
     * Set writer plugin manager
     *
     */
    public static function setWriterPluginManager(ContainerInterface $writers): void
    {
        static::$writers = $writers;
    }

    /**
     * Writes a config to a file
     *
     * @param array|Config $config
     * @return bool TRUE on success | FALSE on failure
     * @throws Exception\RuntimeException
     * @throws Exception\InvalidArgumentException
     * @throws WriterException\InvalidArgumentException
     * @throws WriterException\RuntimeException If the resolved writer cannot write the file
     */
    public static function toFile(string $filename, mixed $config): bool
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

        $writers = self::writerExtensions();

        if (! array_key_exists($extension, $writers)) {
            throw new Exception\RuntimeException(
                "Unsupported config file extension: '.{$extension}' for writing.",
            );
        }

        $writer = $writers[$extension];
        if (! $writer instanceof Writer\AbstractWriter) {
            $resolved = self::getWriterPluginManager()->get($writer);

            if (! $resolved instanceof Writer\AbstractWriter) {
                throw new Exception\RuntimeException(sprintf(
                    "Writer plugin for extension '.%s' is not an instance of %s",
                    $extension,
                    Writer\AbstractWriter::class,
                ));
            }

            $writer                 = $resolved;
            $writers                = self::writerExtensions();
            $writers[$extension]    = $resolved;
            self::$writerExtensions = $writers;
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
     * @template T of object
     * @param class-string<T> $type
     * @return class-string<T>|T
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

    /**
     * Return the path of a config file, trying the include path when the plain name
     * does not exist.
     */
    private static function existingOrIncludePath(string $filename): ?string
    {
        if (file_exists($filename)) {
            return $filename;
        }

        $resolved = stream_resolve_include_path($filename);

        return false === $resolved ? null : $resolved;
    }

    /**
     * The reader extension table for this process.
     *
     * @return array<string, class-string<Reader\ReaderInterface>|Reader\ReaderInterface>
     */
    private static function extensions(): array
    {
        return self::$extensions ??= PluginRegistry::readerExtensions();
    }

    /**
     * Read a config file using the strategy its extension selects.
     *
     * A PHP file is included and must return an array; every other extension is
     * delegated to the reader registered for it.
     *
     * @return array<array-key, mixed>
     * @throws Exception\RuntimeException If the file cannot be read or no reader handles it.
     * @throws \Psr\Container\ContainerExceptionInterface If the reader cannot be resolved.
     * @throws \Psr\Container\NotFoundExceptionInterface If no reader is registered for the plugin.
     */
    private static function readConfig(string $extension, string $filepath, string $filename): array
    {
        if ('php' === $extension) {
            if (! is_file($filepath) || ! is_readable($filepath)) {
                throw new Exception\RuntimeException(sprintf(
                    "File '%s' doesn't exist or not readable",
                    $filename,
                ));
            }

            // @mago-expect analysis:mixed-return-statement
            return include $filepath;
        }

        $extensions = self::extensions();

        if (! array_key_exists($extension, $extensions)) {
            throw new Exception\RuntimeException(sprintf(
                'Unsupported config file extension: .%s',
                $extension,
            ));
        }

        $reader = $extensions[$extension];

        if ($reader instanceof Reader\ReaderInterface) {
            return $reader->fromFile($filepath);
        }

        $resolved = static::getReaderPluginManager()->get($reader);

        if (! $resolved instanceof Reader\ReaderInterface) {
            throw new Exception\RuntimeException(sprintf(
                "Reader plugin for extension '.%s' is not an instance of %s",
                $extension,
                Reader\ReaderInterface::class,
            ));
        }

        $extensions             = self::extensions();
        $extensions[$extension] = $resolved;
        self::$extensions       = $extensions;

        return $resolved->fromFile($filepath);
    }

    /**
     * The writer extension table for this process.
     *
     * @return array<string, class-string<Writer\AbstractWriter>|Writer\AbstractWriter>
     */
    private static function writerExtensions(): array
    {
        return self::$writerExtensions ??= PluginRegistry::writerExtensions();
    }
}
