<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Psl\Exception\ExceptionInterface as PslExceptionInterface;
use Psl\File;
use Psl\Filesystem;
use Throwable;
use Webware\ConfigManager\Exception;

use function rename;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function stripos;
use function umask;

use const PHP_OS;

/**
 * Writes a file by filling an exclusive temporary file and renaming it onto the
 * target, so a reader never observes a partially written document.
 *
 * PSL supplies the primitives: {@see Filesystem\create_temporary_file()} creates
 * the private temporary file, {@see File\write()} fills it, and
 * {@see Filesystem\change_permissions()} sets the final mode. PSL has no rename
 * equivalent, so the swap itself is the one native call.
 */
final class FileWriter
{
    /** Mode applied to the target, before the process umask is applied. */
    private const int MODE = 0o666;

    /**
     * Give up retrying the rename after this many attempts.
     *
     * Windows refuses to rename over a file that another handle has open, and the
     * competing handle is usually a short-lived read, so a bounded retry succeeds
     * in practice where a single attempt would not.
     */
    private const int RENAME_ATTEMPTS = 100;

    /**
     * @param string $filename Callers may pass an empty name, which is rejected.
     * @throws Exception\InvalidArgumentException If the file name is empty.
     * @throws Exception\RuntimeException If the file cannot be written.
     */
    public static function write(string $filename, string $content): void
    {
        if ('' === $filename) {
            throw new Exception\InvalidArgumentException('No file name specified');
        }

        $temporary = self::createTemporary(Filesystem\get_directory($filename));

        try {
            self::fill($temporary, $content);
            self::swap($temporary, $filename);
        } catch (Throwable $throwable) {
            self::discard($temporary);

            throw new Exception\RuntimeException(
                sprintf('Error writing to "%s": %s', $filename, $throwable->getMessage()),
                0,
                $throwable,
            );
        }
    }

    /**
     * @param non-empty-string $directory
     * @return non-empty-string
     * @throws Exception\RuntimeException If a temporary file cannot be created.
     */
    private static function createTemporary(string $directory): string
    {
        try {
            return Filesystem\create_temporary_file(
                directory: $directory,
                prefix   : '.wcm',
            );
        } catch (PslExceptionInterface $exception) {
            throw new Exception\RuntimeException(
                sprintf('Unable to create a temporary file in "%s"', $directory),
                0,
                $exception,
            );
        }
    }

    /**
     * Remove the temporary file, never letting cleanup mask the real failure.
     *
     * @param non-empty-string $temporary
     */
    private static function discard(string $temporary): void
    {
        try {
            Filesystem\delete_file($temporary);
        } catch (PslExceptionInterface) {
            // Best effort: the caller already holds the failure that matters.
            return;
        }
    }

    /**
     * @param non-empty-string $temporary
     * @throws PslExceptionInterface
     */
    private static function fill(string $temporary, string $content): void
    {
        // Truncate is required: OpenOrCreate would leave a tail of the previous
        // document in place whenever the new content is shorter.
        File\write($temporary, $content, File\WriteMode::Truncate);

        // The temporary file is created 0600, so the final mode is set here.
        Filesystem\change_permissions($temporary, self::MODE & ~umask());
    }

    /**
     * @param non-empty-string $filename
     */
    private static function mayRetry(string $filename): bool
    {
        // Only a regular file that another handle has open can be retried; a
        // directory target is a permanent failure.
        return (
            0 === stripos(
                haystack: PHP_OS,
                needle  : 'WIN',
            )
                && Filesystem\is_file($filename)
                && Filesystem\is_writable($filename)
        );
    }

    /**
     * Replace the target, reporting failure through the return value only.
     *
     * A failed rename raises a warning, and this method reports that failure as a
     * typed exception instead, so the warning is not allowed to surface.
     *
     * @param non-empty-string $temporary
     * @param non-empty-string $filename
     */
    private static function rename(string $temporary, string $filename): bool
    {
        set_error_handler(static fn(): bool => true);

        try {
            return rename($temporary, $filename);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param non-empty-string $temporary
     * @param non-empty-string $filename
     * @throws Exception\RuntimeException If the target cannot be replaced.
     */
    private static function swap(string $temporary, string $filename): void
    {
        for ($attempt = 1; $attempt <= self::RENAME_ATTEMPTS; $attempt++) {
            if (self::rename($temporary, $filename)) {
                return;
            }

            if (! self::mayRetry($filename)) {
                break;
            }
        }

        throw new Exception\RuntimeException('unable to replace the file');
    }
}
