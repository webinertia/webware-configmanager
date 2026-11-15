<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Factory;
use Webware\ConfigManager\ReaderPluginManager;
use Webware\ConfigManager\StandaloneReaderPluginManager;
use Webware\ConfigManager\StandaloneWriterPluginManager;
use Webware\ConfigManager\WriterPluginManager;

use function chmod;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function get_include_path;
use function is_writable;
use function mkdir;
use function rmdir;
use function set_include_path;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(Factory::class)]
final class FactoryTest extends TestCase
{
    /** @var array */
    protected $tmpFiles = [];
    /** @var false|string */
    protected $originalIncludePath;

    #[Test]
    public function defaultReaderPluginManagerIsStandaloneVariant()
    {
        $readers = Factory::getReaderPluginManager();
        static::assertInstanceOf(StandaloneReaderPluginManager::class, $readers);
    }

    #[Test]
    public function defaultWriterPluginManagerIsStandaloneVariant()
    {
        $writers = Factory::getWriterPluginManager();
        static::assertInstanceOf(StandaloneWriterPluginManager::class, $writers);
    }

    #[Test]
    public function factoryCanRegisterCustomReaderInstance()
    {
        Factory::registerReader('dum', new Reader\TestAssets\DummyReader());

        $configObject = Factory::fromFile(
            filename          : __DIR__ . '/TestAssets/dummy.dum',
            returnConfigObject: true,
        );
        static::assertInstanceOf(Config::class, $configObject);

        static::assertSame(
            expected: $configObject['one'],
            actual  : 1,
        );
    }

    #[Test]
    public function factoryCanRegisterCustomReaderPlugin()
    {
        $services      = $this->createStub(ContainerInterface::class);
        $pluginManager = new ReaderPluginManager($services, [
            'services' => [
                Reader\TestAssets\DummyReader::class => new Reader\TestAssets\DummyReader(),
            ],
        ]);
        Factory::setReaderPluginManager($pluginManager);
        Factory::registerReader('dum', Reader\TestAssets\DummyReader::class);

        $configObject = Factory::fromFile(
            filename          : __DIR__ . '/TestAssets/dummy.dum',
            returnConfigObject: true,
        );
        static::assertInstanceOf(Config::class, $configObject);

        static::assertSame(
            expected: $configObject['one'],
            actual  : 1,
        );
    }

    #[Test]
    public function factoryCanRegisterCustomWriterInstance()
    {
        Factory::registerWriter('dum', new Writer\TestAssets\DummyWriter());

        $file = $this->getTestAssetFileName('dum');

        $res = Factory::toFile($file, ['one' => 1]);

        static::assertTrue($res);
    }

    #[Test]
    public function factoryCanRegisterCustomWriterPlugin()
    {
        $services      = $this->createStub(ContainerInterface::class);
        $pluginManager = new WriterPluginManager($services, [
            'services' => [
                Writer\TestAssets\DummyWriter::class => new Writer\TestAssets\DummyWriter(),
            ],
        ]);
        Factory::setWriterPluginManager($pluginManager);
        Factory::registerWriter('dum', Writer\TestAssets\DummyWriter::class);

        $file = $this->getTestAssetFileName('dum');

        $res = Factory::toFile($file, ['one' => 1]);
        static::assertTrue($res);
    }

    #[Test]
    public function factoryFromFileRejectsAReaderPluginThatIsNotAReaderInterface()
    {
        // The extension is registered with a valid reader class, but the container
        // that resolves it hands back something that does not implement the reader
        // contract, so the resolved plugin is rejected instead of being used.
        $file     = $this->getTestAssetFileName('dum');
        $services = $this->createStub(ContainerInterface::class);
        $services->method('get')->willReturn(new stdClass());
        Factory::setReaderPluginManager($services);
        Factory::registerReader('dum', Reader\TestAssets\DummyReader::class);
        file_put_contents(
            filename: $file,
            data    : '',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains("Reader plugin for extension '.dum' is not an instance of");

        Factory::fromFile($file);
    }

    #[Test]
    public function factoryRegisterInvalidReader()
    {
        $this->expectException(InvalidArgumentException::class);
        Factory::registerReader('dum', new Writer\TestAssets\DummyWriter());
    }

    #[Test]
    public function factoryRegisterInvalidWriter()
    {
        $this->expectException(InvalidArgumentException::class);
        Factory::registerWriter('dum', new Reader\TestAssets\DummyReader());
    }

    #[Test]
    public function factoryRegisterReaderRejectsAClassThatDoesNotImplementTheInterface()
    {
        $this->expectException(InvalidArgumentException::class);

        Factory::registerReader('dum', stdClass::class);
    }

    #[Test]
    public function factoryRegisterReaderRejectsAPluginName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Reader should be a class-string of');

        Factory::registerReader(
            extension: 'dum',
            reader   : 'ini',
        );
    }

    #[Test]
    public function factoryRegisterWriterRejectsAClassThatIsNotAWriter()
    {
        $this->expectException(InvalidArgumentException::class);

        Factory::registerWriter('dum', stdClass::class);
    }

    #[Test]
    public function factoryRegisterWriterRejectsAPluginName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Writer should be a class-string of');

        Factory::registerWriter(
            extension: 'dum',
            writer   : 'php',
        );
    }

    #[Test]
    public function factoryToFileInvalidFileExtension()
    {
        $this->expectException(RuntimeException::class);
        Factory::toFile(__DIR__ . '/TestAssets/bad.ext', []);
    }

    #[Test]
    public function factoryToFileNoDirInHere()
    {
        $this->expectException(RuntimeException::class);
        Factory::toFile(__DIR__ . '/TestAssets/NoDirInHere/nonExisiting/dummy.php', []);
    }

    #[Test]
    public function factoryToFileRejectsAWriterPluginThatIsNotAnAbstractWriter()
    {
        // The extension is registered with a valid writer class, but the container
        // that resolves it hands back a writer that does not extend AbstractWriter,
        // so the resolved plugin is rejected instead of being used.
        $services = $this->createStub(ContainerInterface::class);
        $services->method('get')->willReturn(new Writer\TestAssets\PlainWriter());
        Factory::setWriterPluginManager($services);
        Factory::registerWriter('plain', Writer\TestAssets\DummyWriter::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('is not an instance of');

        Factory::toFile($this->getTestAssetFileName('plain'), ['one' => 1]);
    }

    #[Test]
    public function factoryToFileRejectsObjectThatIsNotConfig()
    {
        $this->expectException(InvalidArgumentException::class);
        Factory::toFile('test.ini', new stdClass());
    }

    #[Test]
    public function factoryToFileWrongConfig()
    {
        $this->expectException(InvalidArgumentException::class);
        Factory::toFile(
            filename: 'test.ini',
            config  : 'Im wrong',
        );
    }

    #[Test]
    public function factoryWriteToFile()
    {
        $config = ['test' => 'foo', 'bar' => [0 => 'baz', 1 => 'foo']];

        $file   = $this->getTestAssetFileName('php');
        $result = Factory::toFile($file, $config);

        // build string line by line as we are trailing-whitespace sensitive.
        $expected = "<?php\n";
        $expected .= "\n";
        $expected .= "return [\n";
        $expected .= "    'test' => 'foo',\n";
        $expected .= "    'bar'  => [\n";
        $expected .= "        0 => 'baz',\n";
        $expected .= "        1 => 'foo',\n";
        $expected .= "    ],\n";
        $expected .= "];\n";

        static::assertTrue($result);
        static::assertEquals($expected, file_get_contents($file));
    }

    #[Test]
    public function fromFileNotFoundInIncludePathThrows()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains(
            'cannot be found relative to the working directory or the include_path',
        );

        Factory::fromFile(
            filename          : 'this-file-does-not-exist.php',
            returnConfigObject: false,
            useIncludePath    : true,
        );
    }

    #[Test]
    public function fromFileWithoutExtensionThrows()
    {
        $file = sys_get_temp_dir() . '/webware-configmanager-no-extension';
        file_put_contents(
            filename: $file,
            data    : '<?php return [];',
        );

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains('is missing an extension');

            Factory::fromFile($file);
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function fromIni()
    {
        $config = Factory::fromFile(__DIR__ . '/TestAssets/Ini/include-base.ini');

        static::assertSame('bar', $config['base']['foo']);
    }

    #[Test]
    public function fromIniAndXmlAndPhpFiles()
    {
        $files = [
            __DIR__ . '/TestAssets/Ini/include-base.ini',
            __DIR__ . '/TestAssets/Xml/include-base2.xml',
            __DIR__ . '/TestAssets/Php/include-base3.php',
        ];
        $config = Factory::fromFiles($files);

        static::assertSame('bar', $config['base']['foo']);
        static::assertSame('baz', $config['test']['bar']);
        static::assertSame('baz', $config['last']['bar']);
    }

    #[Test]
    public function fromIniAndXmlAndPhpFilesFromIncludePath()
    {
        $files = [
            'Ini/include-base.ini',
            'Xml/include-base2.xml',
            'Php/include-base3.php',
        ];
        $config = Factory::fromFiles(
            files             : $files,
            returnConfigObject: false,
            useIncludePath    : true,
        );

        static::assertSame('bar', $config['base']['foo']);
        static::assertSame('baz', $config['test']['bar']);
        static::assertSame('baz', $config['last']['bar']);
    }

    #[Test]
    public function fromIniFiles()
    {
        $files = [
            __DIR__ . '/TestAssets/Ini/include-base.ini',
            __DIR__ . '/TestAssets/Ini/include-base2.ini',
        ];
        $config = Factory::fromFiles($files);

        static::assertSame('bar', $config['base']['foo']);
        static::assertSame('baz', $config['test']['bar']);
    }

    #[Test]
    public function fromPhpFiles()
    {
        $files = [
            __DIR__ . '/TestAssets/Php/include-base.php',
            __DIR__ . '/TestAssets/Php/include-base2.php',
        ];
        $config = Factory::fromFiles($files);

        static::assertSame('bar', $config['base']['foo']);
        static::assertSame('baz', $config['test']['bar']);
    }

    #[Test]
    public function fromPhpFilesAppliesMergeDirectives()
    {
        $config = Factory::fromFiles([
            __DIR__ . '/TestAssets/Php/directive-base.php',
            __DIR__ . '/TestAssets/Php/directive-override.php',
        ]);

        static::assertSame(
            [
                'keep'    => 'kept',
                'section' => ['replacement' => 'value'],
                'added'   => 'new',
            ],
            $config,
        );
    }

    #[Test]
    public function fromPhpPathThatIsNotAFileThrows()
    {
        $directory = sys_get_temp_dir() . '/webware-configmanager-not-a-file.php';
        mkdir($directory);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageIsOrContains("doesn't exist or not readable");

            Factory::fromFile($directory);
        } finally {
            rmdir($directory);
        }
    }

    #[Test]
    public function fromProvidersAcceptsInstancesAndClosures()
    {
        $config = Factory::fromProviders([
            new MergeTestAssets\FirstConfigProvider(),
            static fn(): array => ['from-closure' => true],
        ]);

        static::assertSame(
            ['shared' => ['a' => 1, 'b' => 2], 'first' => true, 'from-closure' => true],
            $config,
        );
    }

    #[Test]
    public function fromProvidersAppliesMergeDirectivesAcrossProviders()
    {
        $config = Factory::fromProviders([
            MergeTestAssets\FirstConfigProvider::class,
            MergeTestAssets\RemovingConfigProvider::class,
        ]);

        static::assertSame(['first' => true], $config);
    }

    #[Test]
    public function fromProvidersMergesClassStringProvidersAndReturnsAnArray()
    {
        $config = Factory::fromProviders([
            MergeTestAssets\FirstConfigProvider::class,
            MergeTestAssets\SecondConfigProvider::class,
        ]);

        static::assertIsArray($config);
        static::assertSame(
            [
                'shared' => ['a' => 1, 'b' => 3],
                'first'  => true,
                'second' => true,
            ],
            $config,
        );
    }

    #[Test]
    public function fromProvidersRejectsADuplicateProvider()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'Config provider "' . MergeTestAssets\FirstConfigProvider::class . '" was supplied more than once',
        );

        Factory::fromProviders([
            MergeTestAssets\FirstConfigProvider::class,
            MergeTestAssets\FirstConfigProvider::class,
        ]);
    }

    #[Test]
    public function fromProvidersRejectsAProviderThatDoesNotReturnAnArray()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('does not return an array');

        Factory::fromProviders([MergeTestAssets\NotAnArrayConfigProvider::class]);
    }

    #[Test]
    public function fromProvidersReturnsAConfigObjectWhenRequested()
    {
        $config = Factory::fromProviders(
            providers         : [MergeTestAssets\FirstConfigProvider::class],
            returnConfigObject: true,
        );

        static::assertInstanceOf(Config::class, $config);
        static::assertTrue($config->first);
    }

    #[Test]
    public function fromXml()
    {
        $config = Factory::fromFile(__DIR__ . '/TestAssets/Xml/include-base.xml');

        static::assertSame('bar', $config['base']['foo']);
    }

    #[Test]
    public function fromXmlFiles()
    {
        $files = [
            __DIR__ . '/TestAssets/Xml/include-base.xml',
            __DIR__ . '/TestAssets/Xml/include-base2.xml',
        ];
        $config = Factory::fromFiles($files);

        static::assertSame('bar', $config['base']['foo']);
        static::assertSame('baz', $config['test']['bar']);
    }

    #[Test]
    public function nonExistentFileThrowsRuntimeException()
    {
        $this->expectException(RuntimeException::class);
        Factory::fromFile('foo.bar');
    }

    public function resetPluginManagers()
    {
        $reflectionClass = new ReflectionClass(Factory::class);
        $reflectionClass->setStaticPropertyValue(
            name : 'writers',
            value: null,
        );
        $reflectionClass->setStaticPropertyValue(
            name : 'readers',
            value: null,
        );
    }

    #[Test]
    public function returnsConfigObjectIfRequestedAndArrayOtherwise()
    {
        $files = [
            __DIR__ . '/TestAssets/Ini/include-base.ini',
        ];

        $configArray = Factory::fromFile($files[0]);
        static::assertIsArray($configArray);

        $configArray = Factory::fromFiles($files);
        static::assertIsArray($configArray);

        $configObject = Factory::fromFile(
            filename          : $files[0],
            returnConfigObject: true,
        );
        static::assertInstanceOf(Config::class, $configObject);

        $configObject = Factory::fromFiles(
            files             : $files,
            returnConfigObject: true,
        );
        static::assertInstanceOf(Config::class, $configObject);
    }

    #[Test]
    public function unsupportedFileExtensionThrowsRuntimeException()
    {
        $this->expectException(RuntimeException::class);
        Factory::fromFile(__DIR__ . '/TestAssets/bad.ext');
    }

    /**
     * @param string $ext
     * @return mixed|string
     */
    protected function getTestAssetFileName($ext)
    {
        if (null === ($this->tmpFiles[$ext] ?? null)) {
            $this->tmpFiles[$ext] =
                tempnam(
                    directory: sys_get_temp_dir(),
                    prefix   : 'laminas-config-writer',
                )
                . '.'
                . $ext;
        }
        return $this->tmpFiles[$ext];
    }

    protected function setUp(): void
    {
        $this->originalIncludePath = get_include_path();
        set_include_path(__DIR__ . '/TestAssets');
        $this->resetPluginManagers();
    }

    protected function tearDown(): void
    {
        set_include_path($this->originalIncludePath);

        foreach ($this->tmpFiles as $file) {
            if (! file_exists($file)) {
                continue;
            }

            if (! is_writable($file)) {
                chmod(
                    filename   : $file,
                    permissions: 0o777,
                );
            }
            @unlink($file);
        }

        $this->resetPluginManagers();
    }
}
