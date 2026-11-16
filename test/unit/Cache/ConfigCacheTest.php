<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Cache\ConfigCache;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Shape;

use function chmod;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function fopen;
use function is_file;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const PHP_OS_FAMILY;

#[CoversClass(ConfigCache::class)]
#[CoversClass(Shape::class)]
final class ConfigCacheTest extends TestCase
{
    private string $path;

    #[Test]
    public function aCachedConfigIsReadBack(): void
    {
        file_put_contents($this->path, data: "<?php\n\nreturn ['cached' => true];\n");

        static::assertSame(['cached' => true], new ConfigCache($this->path)->load());
    }

    #[Test]
    public function aCacheFileThatDoesNotReturnAnArrayIsRejected(): void
    {
        file_put_contents($this->path, data: "<?php\n\nreturn 'nope';\n");

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            sprintf('Config cache file "%s" must return an array; it returned "string"', $this->path),
        );

        new ConfigCache($this->path)->load();
    }

    #[Test]
    public function aClosureInTheConfigSurvivesTheRoundTrip(): void
    {
        // Closures appear in real configuration, so the cache has to snapshot them. The
        // aggregator used VarExporter with CLOSURE_SNAPSHOT_USES for exactly this.
        new ConfigCache($this->path)->save([
            ConfigCache::ENABLE_CACHE => true,
            'callback'                => static fn(): string => 'snapshotted',
        ]);

        $loaded = new ConfigCache($this->path)->load();

        static::assertIsArray($loaded);
        static::assertArrayHasKey('callback', $loaded);
        static::assertSame('snapshotted', $loaded['callback']());
    }

    #[Test]
    public function aConfigThatCannotBeExportedIsReported(): void
    {
        // A resource has no exportable representation, so the caller is told rather than
        // being left with a stale cache file.
        $handle = fopen(
            filename: 'php://memory',
            mode    : 'r',
        );
        static::assertIsResource($handle);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Unable to export the configuration cache:');
        $this->expectExceptionCode(0);

        new ConfigCache($this->path)->save([
            ConfigCache::ENABLE_CACHE => true,
            'stream'                  => $handle,
        ]);
    }

    #[Test]
    public function aFailedWriteLeavesTheExistingPathUntouched(): void
    {
        // A directory cannot be replaced by the rename, so the write fails and the cache
        // is skipped. The explicit mode must not then be applied to the directory that is
        // still there in place of the file.
        $directory = "{$this->path}-dir";
        mkdir(
            directory  : $directory,
            permissions: 0o700,
        );
        chmod(
            filename   : $directory,
            permissions: 0o700,
        );

        try {
            new ConfigCache($directory)->save([
                ConfigCache::ENABLE_CACHE   => true,
                ConfigCache::CACHE_FILEMODE => 0o777,
            ]);

            static::assertDirectoryExists($directory);

            if ('Windows' !== PHP_OS_FAMILY) {
                static::assertSame(0o700, fileperms($directory) & 0o777);
            }
        } finally {
            rmdir($directory);
        }
    }

    #[Test]
    public function aMissingFileReadsNothing(): void
    {
        static::assertNull(new ConfigCache($this->path)->load());
    }

    #[Test]
    public function anIntegerFileModeIsAccepted(): void
    {
        // Only an integer is applied; on Windows the mode is skipped because a read-only
        // destination cannot be renamed over on the next write.
        new ConfigCache($this->path)->save([
            ConfigCache::ENABLE_CACHE   => true,
            ConfigCache::CACHE_FILEMODE => 0o600,
        ]);

        static::assertFileExists($this->path);
    }

    #[Test]
    public function anIntegerFileModeIsAppliedWhereThePlatformModelsPermissions(): void
    {
        // FileWriter derives a new file's mode from umask, so 0o640 is only present if
        // the explicit mode was applied. Windows does not model POSIX permissions and
        // the writer skips them there.
        if ('Windows' === PHP_OS_FAMILY) {
            static::markTestSkipped('POSIX permissions are not modelled on Windows.');
        }

        new ConfigCache($this->path)->save([
            ConfigCache::ENABLE_CACHE   => true,
            ConfigCache::CACHE_FILEMODE => 0o640,
        ]);

        static::assertSame(0o640, fileperms($this->path) & 0o777);
    }

    #[Test]
    public function aNonIntegerFileModeIsIgnored(): void
    {
        new ConfigCache($this->path)->save([
            ConfigCache::ENABLE_CACHE   => true,
            ConfigCache::CACHE_FILEMODE => '0600',
        ]);

        static::assertFileExists($this->path);
    }

    #[Test]
    public function aNullPathReadsNothing(): void
    {
        static::assertNull(new ConfigCache(null)->load());
    }

    #[Test]
    public function aNullPathWritesNothing(): void
    {
        new ConfigCache(null)->save([ConfigCache::ENABLE_CACHE => true]);

        static::assertFalse(is_file($this->path));
    }

    #[Test]
    public function anUnwritableCacheFileDoesNotStopTheApplication(): void
    {
        // Parity with the aggregator: a cache that cannot be written costs performance,
        // not correctness, so the failure is swallowed.
        $unwritable =
            sys_get_temp_dir()
            . '/wcm-missing-'
            . uniqid(
                prefix      : '',
                more_entropy: true,
            )
            . '/config-cache.php';

        new ConfigCache($unwritable)->save([ConfigCache::ENABLE_CACHE => true]);

        static::assertFileDoesNotExist($unwritable);
    }

    #[Test]
    public function onlyTheBooleanTrueEnablesTheCache(): void
    {
        // Deliberately stricter than the aggregator's empty() check: a mis-typed setting
        // must not silently turn caching on.
        new ConfigCache($this->path)->save([ConfigCache::ENABLE_CACHE => 1]);

        static::assertFileDoesNotExist($this->path);
    }

    #[Test]
    public function theCacheIsNotWrittenWhenTheSettingIsAbsent(): void
    {
        new ConfigCache($this->path)->save(['value' => 'uncached']);

        static::assertFileDoesNotExist($this->path);
    }

    #[Test]
    public function theCacheIsNotWrittenWhenTheSettingIsFalse(): void
    {
        new ConfigCache($this->path)->save([ConfigCache::ENABLE_CACHE => false]);

        static::assertFileDoesNotExist($this->path);
    }

    #[Test]
    public function theCacheIsWrittenWhenTheSettingIsTrue(): void
    {
        new ConfigCache($this->path)->save([
            ConfigCache::ENABLE_CACHE => true,
            'value'                   => 'cached',
        ]);

        static::assertFileExists($this->path);
        static::assertSame(
            ['config_cache_enabled' => true, 'value' => 'cached'],
            new ConfigCache($this->path)->load(),
        );
    }

    #[Test]
    public function theWrittenFileIsPhpReturningTheConfig(): void
    {
        new ConfigCache($this->path)->save([ConfigCache::ENABLE_CACHE => true]);

        $contents = file_get_contents($this->path);

        static::assertIsString($contents);
        static::assertStringStartsWith("<?php\n", $contents);
        static::assertStringContainsString('return ', $contents);
    }

    protected function setUp(): void
    {
        $this->path =
            sys_get_temp_dir()
            . '/wcm-cache-'
            . uniqid(
                prefix      : '',
                more_entropy: true,
            )
            . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}
