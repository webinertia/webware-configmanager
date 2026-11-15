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
use function umask;

use const PHP_OS_FAMILY;

/**
 * Writes a file by filling an exclusive temporary file and renaming it onto the
 * target, so a reader never observes a partially written document.
 *
 * PSL supplies the primitives: {@see Filesystem\create_temporary_file()} creates
 * the private temporary file, {@see File\write()} fills it,
 * {@see Filesystem\change_permissions()} sets the final mode, and
 * {@see Filesystem\change_owner()} / {@see Filesystem\change_group()} carry the
 * existing ownership over. PSL has no rename equivalent, so the swap itself is
 * the one native call.
 */
// The steps are one operation each, kept separate so their failure can be handled
// differently, which is clearer than a single method that branches four ways.
// @mago-expect lint:too-many-methods
final class FileWriter
{
    /** Mode applied to a new file, before the process umask is applied. */
    private const int MODE = 0o666;

    /** The permission bits of a file, without its type bits. */
    private const int PERMISSION_MASK = 0o7777;

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
            self::adoptAttributes($temporary, $filename);
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
     * Give the temporary file the attributes of the document it replaces.
     *
     * A rename replaces the inode, so without this the new file belongs to
     * whichever user ran PHP and carries a mode derived from umask. A configuration
     * file is usually owned by the deployer rather than by the web server, and its
     * mode is often restrictive on purpose, so both are carried over instead. A new
     * file gets MODE narrowed by umask.
     *
     * @param non-empty-string $temporary
     * @param non-empty-string $filename
     * @throws PslExceptionInterface
     */
    private static function adoptAttributes(string $temporary, string $filename): void
    {
        self::inheritAttributes(
            $temporary,
            $filename,
            posixPermissions: 'Windows' !== PHP_OS_FAMILY,
        );
    }

    /**
     * @param non-empty-string $temporary
     * @param non-empty-string $filename
     */
    private static function adoptGroup(string $temporary, string $filename): void
    {
        try {
            Filesystem\change_group($temporary, Filesystem\get_group($filename));
        } catch (PslExceptionInterface) {
            // Unless the process already belongs to the target group.
            return;
        }
    }

    /**
     * @param non-empty-string $temporary
     * @param non-empty-string $filename
     */
    private static function adoptOwner(string $temporary, string $filename): void
    {
        try {
            Filesystem\change_owner($temporary, Filesystem\get_owner($filename));
        } catch (PslExceptionInterface) {
            // Only a privileged process can hand a file to another user.
            return;
        }
    }

    /**
     * Hand the temporary file to the owner and group of the document it replaces.
     *
     * Ownership needs privilege, so this is best effort: a host that runs PHP as a
     * different user than the deployer still needs its file ownership fixed, and a
     * configuration write must not fail because of that, so an unprivileged process
     * keeps the file it created.
     *
     * @param non-empty-string $temporary
     * @param non-empty-string $filename
     */
    private static function adoptOwnership(string $temporary, string $filename): void
    {
        // Attempted separately: a group can be settable where ownership cannot, so a
        // refused ownership change must not skip the group change.
        self::adoptOwner($temporary, $filename);
        self::adoptGroup($temporary, $filename);
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
    }

    /**
     * @param non-empty-string $temporary
     * @param non-empty-string $filename
     * @param bool $posixPermissions Whether the platform models POSIX permission bits.
     * @throws PslExceptionInterface
     */
    // @mago-expect lint:no-boolean-flag-parameter
    private static function inheritAttributes(
        string $temporary,
        string $filename,
        bool $posixPermissions,
    ): void {
        if (! $posixPermissions) {
            // Windows does not model POSIX permissions: chmod there toggles only the
            // read-only bit, and a read-only destination cannot be renamed over, so
            // adopting a mode would break the next write. Ownership is not settable
            // without elevation either. The temporary file keeps the mode it was
            // created with, which on Windows means writable.
            return;
        }

        if (! Filesystem\is_file($filename)) {
            Filesystem\change_permissions($temporary, self::MODE & ~umask());

            return;
        }

        Filesystem\change_permissions(
            $temporary,
            Filesystem\get_permissions($filename) & self::PERMISSION_MASK,
        );

        self::adoptOwnership($temporary, $filename);
    }

    /**
     * @param non-empty-string $filename
     */
    private static function mayRetry(string $filename): bool
    {
        // Only a regular file that another handle has open can be retried; a
        // directory target is a permanent failure.
        return (
            'Windows' === PHP_OS_FAMILY
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
