<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Cache\ConfigCache;
use Webware\ConfigManager\ConfigAggregator;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Provider\ArrayProvider;
use WebwareTest\ConfigManager\Provider\TestAssets\ArrayConfigProvider;

use function file_put_contents;
use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

#[CoversClass(ConfigAggregator::class)]
final class ConfigAggregatorTest extends TestCase
{
    private string $path;

    #[Test]
    public function aClassStringProviderIsInstantiated(): void
    {
        // ArrayConfigProvider takes no constructor arguments, which is what makes it
        // usable as a class-string, unlike ArrayProvider whose config is injected.
        $aggregator = new ConfigAggregator([ArrayConfigProvider::class]);

        static::assertSame([], $aggregator->getMergedConfig());
    }

    #[Test]
    public function anEmptyProviderListMergesToAnEmptyConfig(): void
    {
        static::assertSame([], new ConfigAggregator()->getMergedConfig());
    }

    #[Test]
    public function anInvalidProviderIsReported(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Config provider must be a class-string or a callable; received "array"');

        new ConfigAggregator([[]]);
    }

    #[Test]
    public function aNullCachePathNeitherReadsNorWrites(): void
    {
        $aggregator = new ConfigAggregator(
            [new ArrayProvider([ConfigCache::ENABLE_CACHE => true, 'value' => 'fresh'])],
            null,
        );

        static::assertSame(
            ['config_cache_enabled' => true, 'value' => 'fresh'],
            $aggregator->getMergedConfig(),
        );
        static::assertFileDoesNotExist($this->path);
    }

    #[Test]
    public function providersAreMergedInTheOrderGiven(): void
    {
        $aggregator = new ConfigAggregator([
            new ArrayProvider(['first' => true, 'shared' => ['a' => 1]]),
            new ArrayProvider(['shared' => ['b' => 2]]),
        ]);

        static::assertSame(
            ['first' => true, 'shared' => ['a' => 1, 'b' => 2]],
            $aggregator->getMergedConfig(),
        );
    }

    #[Test]
    public function theCacheConstantsAreReachableOnTheAggregator(): void
    {
        // An application's config files reference these by class name, so the names have
        // to resolve after the class is swapped.
        static::assertSame('config_cache_enabled', ConfigAggregator::ENABLE_CACHE);
        static::assertSame('config_cache_filemode', ConfigAggregator::CACHE_FILEMODE);
    }

    #[Test]
    public function theCacheIsReadEvenWhenTheSettingIsFalse(): void
    {
        // Parity with the aggregator: disabling the cache prevents writing, not reading.
        file_put_contents($this->path, data: "<?php\n\nreturn ['value' => 'stale'];\n");

        $aggregator = new ConfigAggregator([new ArrayProvider(['value' => 'fresh'])], $this->path);

        static::assertSame(['value' => 'stale'], $aggregator->getMergedConfig());
    }

    #[Test]
    public function theCacheIsWrittenThenReadInsteadOfRunningProviders(): void
    {
        $first = new ConfigAggregator(
            [new ArrayProvider([ConfigCache::ENABLE_CACHE => true, 'value' => 'from-providers'])],
            $this->path,
        );

        static::assertSame(
            ['config_cache_enabled' => true, 'value' => 'from-providers'],
            $first->getMergedConfig(),
        );
        static::assertFileExists($this->path);

        // A provider that would change the output proves the providers never ran.
        $second = new ConfigAggregator(
            [new ArrayProvider([ConfigCache::ENABLE_CACHE => true, 'value' => 'from-providers-again'])],
            $this->path,
        );

        static::assertSame(
            ['config_cache_enabled' => true, 'value' => 'from-providers'],
            $second->getMergedConfig(),
        );
    }

    protected function setUp(): void
    {
        $this->path =
            sys_get_temp_dir()
            . '/wcm-aggregator-'
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
