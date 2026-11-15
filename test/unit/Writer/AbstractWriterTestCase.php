<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer;

use ArrayIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception\InvalidArgumentException;
use Webware\ConfigManager\Exception\RuntimeException;
use Webware\ConfigManager\Reader\ReaderInterface;
use Webware\ConfigManager\Writer\WriterInterface;

use function chmod;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function getenv;
use function is_writable;
use function posix_geteuid;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_OS_FAMILY;

abstract class AbstractWriterTestCase extends TestCase
{
    /** @var ReaderInterface */
    protected $reader;

    /** @var WriterInterface */
    protected $writer;

    /** @var string */
    protected $tmpfile;

    /**
     * Whether the process bypasses file and directory permissions.
     *
     * A read-only destination proves nothing for such a user: it can replace the
     * file regardless of the mode, so the platform difference is not observable.
     */
    private static function isPrivileged(): bool
    {
        if (function_exists('posix_geteuid')) {
            return 0 === posix_geteuid();
        }

        // No POSIX extension available, so fall back to the environment. This is the
        // only signal on a platform that has no uid, and Windows is not privileged
        // here in the sense that matters.
        return 'Windows' !== PHP_OS_FAMILY && 'root' === getenv('USER');
    }

    #[Test]
    public function testFileNotValid()
    {
        $this->expectException(RuntimeException::class);
        $this->writer->toFile('.', new Config([]));
    }

    #[Test]
    public function testNoFilenameSet()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No file name specified');
        $this->writer->toFile('', '');
    }

    #[Test]
    public function testReadOnlyDestination()
    {
        if (self::isPrivileged()) {
            static::markTestSkipped(
                'A privileged user replaces a read-only destination, so the outcome is not observable',
            );
        }

        $testAssetFileName = $this->getTestAssetFileName();
        file_put_contents(
            filename: $testAssetFileName,
            data    : 'previous document',
        );
        chmod(
            filename   : $testAssetFileName,
            permissions: 0o444,
        );

        // The destination is replaced by renaming a sibling temporary file over it,
        // which needs write permission on the directory rather than on the file, so
        // POSIX replaces a read-only destination. Windows refuses to rename over a
        // read-only file, so there the write still fails.
        if ('Windows' === PHP_OS_FAMILY) {
            $this->expectException(RuntimeException::class);
        }

        $this->writer->toFile($testAssetFileName, new Config(['test' => 'foo']));

        static::assertNotSame('previous document', file_get_contents($testAssetFileName));
    }

    #[Test]
    public function testToStringAcceptsATraversableConfig()
    {
        $content = $this->writer->toString(new ArrayIterator(['test' => 'foo']));

        static::assertNotSame('', $content);
    }

    #[Test]
    public function testToStringRejectsAConfigThatIsNeitherArrayNorTraversable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expects an array or Traversable config');

        $this->writer->toString('not a configuration');
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
