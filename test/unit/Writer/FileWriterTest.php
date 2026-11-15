<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Throwable;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Writer\FileWriter;

use function chmod;
use function clearstatcache;
use function file_get_contents;
use function file_put_contents;
use function fileinode;
use function fileperms;
use function glob;
use function is_dir;
use function is_file;
use function is_writable;
use function link;
use function mkdir;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function umask;
use function unlink;

use const PHP_OS_FAMILY;

#[CoversClass(FileWriter::class)]
final class FileWriterTest extends TestCase
{
    /** @var list<string> */
    private array $created = [];

    private string $directory;

    private static function tryLink(string $source, string $destination): bool
    {
        set_error_handler(static fn(): bool => true);

        try {
            return link($source, $destination);
        } catch (Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function testAnExistingDestinationKeepsItsMode(): void
    {
        if ('Windows' === PHP_OS_FAMILY) {
            static::markTestSkipped('Windows does not model POSIX file modes');
        }

        $file = $this->path('target.txt');
        file_put_contents(
            filename: $file,
            data    : 'previous document',
        );
        chmod(
            filename   : $file,
            permissions: 0o640,
        );

        FileWriter::write($file, 'new document');

        clearstatcache();
        static::assertSame(0o640, fileperms($file) & 0o7777);
    }

    #[Test]
    public function testAttributesAreNotAdoptedWherePosixPermissionsAreUnavailable(): void
    {
        $inherit     = new ReflectionMethod(FileWriter::class, 'inheritAttributes');
        $destination = $this->path('target.txt');
        file_put_contents(
            filename: $destination,
            data    : 'previous document',
        );
        chmod(
            filename   : $destination,
            permissions: 0o644,
        );

        $temporary = $this->path('.wcmprobe');
        file_put_contents(
            filename: $temporary,
            data    : 'new document',
        );

        $inherit->invoke(null, $temporary, $destination, false);

        // On Windows chmod toggles only the read-only bit, so adopting the
        // destination's mode there could leave the file unrenameable and break the
        // next write. The temporary file must be left as it was created.
        clearstatcache();
        static::assertSame('new document', file_get_contents($temporary));
        static::assertTrue(is_writable($temporary));
    }

    #[Test]
    public function testFailureToRemoveTheTemporaryFileIsSwallowed(): void
    {
        $discard = new ReflectionMethod(FileWriter::class, 'discard');
        $missing = $this->path('missing.txt');

        // Cleanup runs while the caller holds the failure that caused it, so a
        // cleanup failure must not escape and replace that failure. A directory is
        // not a file and an absent path is not found, so both attempts fail.
        $discard->invoke(null, $this->directory);
        $discard->invoke(null, $missing);

        static::assertFileDoesNotExist($missing);
    }

    #[Test]
    public function testLeavesNoTemporaryFileBehind(): void
    {
        $file = $this->path('target.txt');

        FileWriter::write($file, 'content');

        static::assertSame([], $this->leftovers());
    }

    #[Test]
    public function testLeavesNoTemporaryFileBehindWhenTheWriteFails(): void
    {
        $directory = $this->path('nested');
        mkdir($directory);

        try {
            FileWriter::write($directory, 'content');
            static::fail('Writing to a directory should have failed');
        } catch (Exception\RuntimeException $exception) {
            static::assertStringContainsString('unable to replace the file', $exception->getMessage());
        } finally {
            rmdir($directory);
        }

        static::assertSame([], $this->leftovers($directory));
    }

    #[Test]
    public function testOwnershipThatCannotBeRestoredIsNotFatal(): void
    {
        $adoptOwnership = new ReflectionMethod(FileWriter::class, 'adoptOwnership');
        $destination    = $this->path('target.txt');
        file_put_contents(
            filename: $destination,
            data    : 'previous document',
        );

        // A temporary file that does not exist stands in for a host where ownership
        // cannot be restored: every attempt fails, and the write must still stand.
        $adoptOwnership->invoke(null, $this->path('gone.txt'), $destination);

        static::assertFileExists($destination);
    }

    #[Test]
    public function testPosixInheritanceGivesANewDestinationTheDefaultMode(): void
    {
        $temporary = $this->path('.wcmprobe');
        file_put_contents(
            filename: $temporary,
            data    : 'new document',
        );
        chmod(
            filename   : $temporary,
            permissions: 0o600,
        );

        $inherit = new ReflectionMethod(FileWriter::class, 'inheritAttributes');
        $inherit->invoke(null, $temporary, $this->path('missing.txt'), true);

        static::assertFileExists($temporary);

        if ('Windows' !== PHP_OS_FAMILY) {
            clearstatcache();
            static::assertSame(0o666 & ~umask(), fileperms($temporary) & 0o7777);
        }
    }

    #[Test]
    public function testPosixInheritanceGivesTheTemporaryFileTheExistingMode(): void
    {
        $destination = $this->path('target.txt');
        file_put_contents(
            filename: $destination,
            data    : 'previous document',
        );
        chmod(
            filename   : $destination,
            permissions: 0o640,
        );

        $temporary = $this->path('.wcmprobe');
        file_put_contents(
            filename: $temporary,
            data    : 'new document',
        );

        $inherit = new ReflectionMethod(FileWriter::class, 'inheritAttributes');
        $inherit->invoke(null, $temporary, $destination, true);

        static::assertSame('new document', file_get_contents($temporary));

        if ('Windows' !== PHP_OS_FAMILY) {
            clearstatcache();
            static::assertSame(0o640, fileperms($temporary) & 0o7777);
        }
    }

    #[Test]
    public function testRejectsAnEmptyFilename(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('No file name specified');

        FileWriter::write('', 'content');
    }

    #[Test]
    public function testReplacesTheFileInsteadOfModifyingItInPlace(): void
    {
        $file = $this->path('target.txt');
        $link = $this->path('sibling.txt');

        file_put_contents(
            filename: $file,
            data    : 'original',
        );

        if (! self::tryLink($file, $link)) {
            static::markTestSkipped('The filesystem does not support hard links');
        }

        $inode = fileinode($file);

        FileWriter::write($file, 'replacement');

        static::assertSame('replacement', file_get_contents($file));
        static::assertNotSame($inode, fileinode($file), 'The target inode should be replaced');

        // The sibling name still refers to the previous inode, which proves the
        // write did not go through the existing file.
        static::assertSame('original', file_get_contents($link));
    }

    #[Test]
    public function testShorterContentLeavesNoTrailingBytes(): void
    {
        $file = $this->path('target.txt');

        FileWriter::write($file, 'a much longer document than the next one');
        FileWriter::write($file, 'short');

        static::assertSame('short', file_get_contents($file));
        static::assertSame(5, strlen((string) file_get_contents($file)));
    }

    #[Test]
    public function testThrowsWhenNoTemporaryFileCanBeCreated(): void
    {
        // The parent of the target is a regular file, so no temporary file can be
        // created beside the target.
        $parent = $this->path('parent.txt');
        file_put_contents(
            filename: $parent,
            data    : 'not a directory',
        );

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to create a temporary file in');

        FileWriter::write("{$parent}/target.txt", 'content');
    }

    #[Test]
    public function testThrowsWhenTheTargetDirectoryDoesNotExist(): void
    {
        $this->expectException(Exception\RuntimeException::class);

        FileWriter::write("{$this->directory}/missing/file.txt", 'content');
    }

    #[Test]
    public function testThrowsWhenTheTargetIsADirectory(): void
    {
        $this->expectException(Exception\RuntimeException::class);

        FileWriter::write($this->directory, 'content');
    }

    #[Test]
    public function testWritesContent(): void
    {
        $file = $this->path('target.txt');

        FileWriter::write($file, 'written');

        static::assertSame('written', file_get_contents($file));
    }

    protected function setUp(): void
    {
        $directory = tempnam(
            directory: sys_get_temp_dir(),
            prefix   : 'wcmfw',
        );

        self::assertIsString($directory);
        unlink($directory);
        mkdir($directory);

        $this->directory = $directory;
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $file) {
            if (! is_file($file)) {
                continue;
            }

            chmod(
                filename   : $file,
                permissions: 0o644,
            );
            unlink($file);
        }

        foreach ($this->leftovers() as $leftover) {
            unlink($leftover);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        $this->created = [];
    }

    /**
     * @return list<string>
     */
    private function leftovers(?string $directory = null): array
    {
        $matches = glob(pattern: ($directory ?? $this->directory) . '/.wcm*');

        if (false === $matches) {
            return [];
        }

        return $matches;
    }

    private function path(string $name): string
    {
        $path          = "{$this->directory}/{$name}";
        $this->created = [...$this->created, $path];

        return $path;
    }
}
