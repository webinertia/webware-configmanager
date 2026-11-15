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

    public function resetPluginManagers()
    {
        $reflectionClass = new ReflectionClass(Factory::class);
        $reflectionClass->setStaticPropertyValue('writers', null);
        $reflectionClass->setStaticPropertyValue('readers', null);
    }

    #[Test]
    public function testDefaultReaderPluginManagerIsStandaloneVariant()
    {
        $readers = Factory::getReaderPluginManager();
        static::assertInstanceOf(StandaloneReaderPluginManager::class, $readers);
    }

    #[Test]
    public function testDefaultWriterPluginManagerIsStandaloneVariant()
    {
        $writers = Factory::getWriterPluginManager();
        static::assertInstanceOf(StandaloneWriterPluginManager::class, $writers);
    }

    #[Test]
    public function testFactoryCanRegisterCustomReaderInstance()
    {
        Factory::registerReader('dum', new Reader\TestAssets\DummyReader());

        $configObject = Factory::fromFile(__DIR__ . '/TestAssets/dummy.dum', true);
        static::assertInstanceOf(Config::class, $configObject);

        static::assertSame($configObject['one'], 1);
    }

    #[Test]
    public function testFactoryCanRegisterCustomReaderPlugin()
    {
        $services      = $this->createStub(ContainerInterface::class);
        $pluginManager = new ReaderPluginManager($services, [
            'services' => [
                Reader\TestAssets\DummyReader::class => new Reader\TestAssets\DummyReader(),
            ],
        ]);
        Factory::setReaderPluginManager($pluginManager);
        Factory::registerReader('dum', Reader\TestAssets\DummyReader::class);

        $configObject = Factory::fromFile(__DIR__ . '/TestAssets/dummy.dum', true);
        static::assertInstanceOf(Config::class, $configObject);

        static::assertSame($configObject['one'], 1);
    }

    #[Test]
    public function testFactoryCanRegisterCustomWriterInstance()
    {
        Factory::registerWriter('dum', new Writer\TestAssets\DummyWriter());

        $file = $this->getTestAssetFileName('dum');

        $res = Factory::toFile($file, ['one' => 1]);

        static::assertTrue($res);
    }

    #[Test]
    public function testFactoryCanRegisterCustomWriterPlugin()
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
    public function testFactoryRegisterInvalidReader()
    {
        $this->expectException(InvalidArgumentException::class);
        Factory::registerReader('dum', new Writer\TestAssets\DummyWriter());
    }

    #[Test]
    public function testFactoryRegisterInvalidWriter()
    {
        $this->expectException(InvalidArgumentException::class);
        Factory::registerWriter('dum', new Reader\TestAssets\DummyReader());
    }

    #[Test]
    public function testFactoryRegisterReaderRejectsAClassThatDoesNotImplementTheInterface()
    {
        $this->expectException(InvalidArgumentException::class);

        Factory::registerReader('dum', stdClass::class);
    }

    #[Test]
    public function testFactoryRegisterReaderRejectsAPluginName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reader should be a class-string of');

        Factory::registerReader('dum', 'ini');
    }

    #[Test]
    public function testFactoryRegisterWriterRejectsAClassThatIsNotAWriter()
    {
        $this->expectException(InvalidArgumentException::class);

        Factory::registerWriter('dum', stdClass::class);
    }

    #[Test]
    public function testFactoryRegisterWriterRejectsAPluginName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Writer should be a class-string of');

        Factory::registerWriter('dum', 'php');
    }

    #[Test]
    public function testFactoryToFileInvalidFileExtension()
    {
        $this->expectException(RuntimeException::class);
        Factory::toFile(__DIR__ . '/TestAssets/bad.ext', []);
    }

    #[Test]
    public function testFactoryToFileNoDirInHere()
    {
        $this->expectException(RuntimeException::class);
        Factory::toFile(__DIR__ . '/TestAssets/NoDirInHere/nonExisiting/dummy.php', []);
    }

    #[Test]
    public function testFactoryToFileRejectsAWriterPluginThatIsNotAnAbstractWriter()
    {
        // The extension is registered with a valid writer class, but the container
        // that resolves it hands back a writer that does not extend AbstractWriter,
        // so the resolved plugin is rejected instead of being used.
        $services = $this->createStub(ContainerInterface::class);
        $services->method('get')->willReturn(new Writer\TestAssets\PlainWriter());
        Factory::setWriterPluginManager($services);
        Factory::registerWriter('plain', Writer\TestAssets\DummyWriter::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not an instance of');

        Factory::toFile($this->getTestAssetFileName('plain'), ['one' => 1]);
    }

    #[Test]
    public function testFactoryToFileRejectsObjectThatIsNotConfig()
    {
        $this->expectException(InvalidArgumentException::class);
        Factory::toFile('test.ini', new stdClass());
    }

    #[Test]
    public function testFactoryToFileWrongConfig()
    {
        $this->expectException(InvalidArgumentException::class);
        Factory::toFile('test.ini', 'Im wrong');
    }

    #[Test]
    public function testFactoryWriteToFile()
    {
        $config = ['test' => 'foo', 'bar' => [0 => 'baz', 1 => 'foo']];

        $file   = $this->getTestAssetFileName('php');
        $result = Factory::toFile($file, $config);

        // build string line by line as we are trailing-whitespace sensitive.
        $expected = "<?php\n";
        $expected .= "return array(\n";
        $expected .= "    'test' => 'foo',\n";
        $expected .= "    'bar' => array(\n";
        $expected .= "        0 => 'baz',\n";
        $expected .= "        1 => 'foo',\n";
        $expected .= "    ),\n";
        $expected .= ");\n";

        static::assertTrue($result);
        static::assertEquals($expected, file_get_contents($file));
    }

    #[Test]
    public function testFromFileNotFoundInIncludePathThrows()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be found relative to the working directory or the include_path');

        Factory::fromFile('this-file-does-not-exist.php', false, true);
    }

    #[Test]
    public function testFromFileWithoutExtensionThrows()
    {
        $file = sys_get_temp_dir() . '/webware-configmanager-no-extension';
        file_put_contents(
            filename: $file,
            data    : '<?php return [];',
        );

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('is missing an extension');

            Factory::fromFile($file);
        } finally {
            unlink($file);
        }
    }

    #[Test]
    public function testFromIni()
    {
        $config = Factory::fromFile(__DIR__ . '/TestAssets/Ini/include-base.ini');

        static::assertSame('bar', $config['base']['foo']);
    }

    #[Test]
    public function testFromIniAndXmlAndPhpFiles()
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
    public function testFromIniAndXmlAndPhpFilesFromIncludePath()
    {
        $files = [
            'Ini/include-base.ini',
            'Xml/include-base2.xml',
            'Php/include-base3.php',
        ];
        $config = Factory::fromFiles($files, false, true);

        static::assertSame('bar', $config['base']['foo']);
        static::assertSame('baz', $config['test']['bar']);
        static::assertSame('baz', $config['last']['bar']);
    }

    #[Test]
    public function testFromIniFiles()
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
    public function testFromPhpFiles()
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
    public function testFromPhpPathThatIsNotAFileThrows()
    {
        $directory = sys_get_temp_dir() . '/webware-configmanager-not-a-file.php';
        mkdir($directory);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage("doesn't exist or not readable");

            Factory::fromFile($directory);
        } finally {
            rmdir($directory);
        }
    }

    #[Test]
    public function testFromXml()
    {
        $config = Factory::fromFile(__DIR__ . '/TestAssets/Xml/include-base.xml');

        static::assertSame('bar', $config['base']['foo']);
    }

    #[Test]
    public function testFromXmlFiles()
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
    public function testNonExistentFileThrowsRuntimeException()
    {
        $this->expectException(RuntimeException::class);
        Factory::fromFile('foo.bar');
    }

    #[Test]
    public function testReturnsConfigObjectIfRequestedAndArrayOtherwise()
    {
        $files = [
            __DIR__ . '/TestAssets/Ini/include-base.ini',
        ];

        $configArray = Factory::fromFile($files[0]);
        static::assertIsArray($configArray);

        $configArray = Factory::fromFiles($files);
        static::assertIsArray($configArray);

        $configObject = Factory::fromFile($files[0], true);
        static::assertInstanceOf(Config::class, $configObject);

        $configObject = Factory::fromFiles($files, true);
        static::assertInstanceOf(Config::class, $configObject);
    }

    #[Test]
    public function testUnsupportedFileExtensionThrowsRuntimeException()
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
