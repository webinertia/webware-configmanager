<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception\InvalidArgumentException;
use Webware\ConfigManager\Exception\RuntimeException;
use Webware\ConfigManager\Reader\ReaderInterface;
use Webware\ConfigManager\Writer\WriterInterface;

use function chmod;
use function file_exists;
use function getenv;
use function is_writable;
use function sys_get_temp_dir;
use function system;
use function tempnam;
use function unlink;

abstract class AbstractWriterTestCase extends TestCase
{
    /** @var ReaderInterface */
    protected $reader;

    /** @var WriterInterface */
    protected $writer;

    /** @var string */
    protected $tmpfile;

    #[Test]
    public function testFileNotValid()
    {
        $this->expectException(RuntimeException::class);
        $this->writer->toFile('.', new Config([]));
    }

    #[Test]
    public function testFileNotWritable()
    {
        $testAssetFileName = $this->getTestAssetFileName();
        chmod(
            filename   : $testAssetFileName,
            permissions: 0o444,
        );
        if (getenv('USER') === 'root') {
            system("chattr +i {$testAssetFileName}");
        }
        $this->expectException(RuntimeException::class);
        $this->writer->toFile($testAssetFileName, new Config([]));
    }

    #[Test]
    public function testNoFilenameSet()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No file name specified');
        $this->writer->toFile('', '');
    }

    #[Test]
    public function testWriteAndRead()
    {
        $config = new Config(['default' => ['test' => 'foo']]);

        $this->writer->toFile($this->getTestAssetFileName(), $config);

        $config = $this->reader->fromFile($this->getTestAssetFileName());

        static::assertSame('foo', $config['default']['test']);
    }

    /**
     * Get test asset name for current test case.
     *
     * @return string
     */
    protected function getTestAssetFileName()
    {
        if (null === $this->tmpfile) {
            $this->tmpfile = tempnam(
                directory: sys_get_temp_dir(),
                prefix   : 'laminas-config-writer',
            );
        }
        return $this->tmpfile;
    }

    protected function tearDown(): void
    {
        $testAssetFileName = $this->getTestAssetFileName();
        if (file_exists($testAssetFileName)) {
            if (getenv('USER') === 'root') {
                system("chattr -i {$testAssetFileName}");
            }
            if (! is_writable($testAssetFileName)) {
                chmod(
                    filename   : $testAssetFileName,
                    permissions: 0o777,
                );
            }
            @unlink($testAssetFileName);
        }
    }
}
