<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Cache\ConfigCache;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\ConfigAggregator;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Provider\ArrayProvider;
use Webware\ConfigManager\Shape;
use WebwareTest\ConfigManager\Processor\TestAssets\AddsInjectedProviderPreProcessor;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\BaseKeyProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\WinningProvider;
use WebwareTest\ConfigManager\Provider\TestAssets\ArrayConfigProvider;

use function file_put_contents;
use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

#[CoversClass(ConfigAggregator::class)]
#[CoversClass(Shape::class)]
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
    public function aMergedConfigIsReadOnly(): void
    {
        $aggregator = new ConfigAggregator([new ArrayProvider(['value' => 'merged'])]);

        static::assertTrue($aggregator->isReadOnly());
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Config is read only');

        $aggregator['value'] = 'overwritten';
    }

    #[Test]
    public function anEmptyProviderListMergesToAnEmptyConfig(): void
    {
        static::assertSame([], new ConfigAggregator()->getMergedConfig());
    }

    #[Test]
    public function aNestedArrayInAMergedConfigIsAPlainConfig(): void
    {
        // The constructor of this class takes a provider list, so a nested value cannot be
        // built by forwarding to it. If it were, this would raise a TypeError.
        $aggregator = new ConfigAggregator([
            new ArrayProvider(['dependencies' => ['factories' => ['Service' => 'Factory']]]),
        ]);

        $nested = $aggregator->get('dependencies');

        static::assertInstanceOf(Config::class, $nested);
        static::assertNotInstanceOf(ConfigAggregator::class, $nested);
        static::assertSame(['factories' => ['Service' => 'Factory']], $nested->toArray());
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
    public function anUnusableProcessorIsReported(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('A config processor must be a class-string or a callable; received "int"');

        new ConfigAggregator([new ArrayProvider([])], null, [42]);
    }

    #[Test]
    public function aPostProcessorThatDoesNotReturnAnArrayIsReported(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'A config post-processor must return an array; it returned "string"',
        );

        new ConfigAggregator([new ArrayProvider([])], null, [static fn(array $c): string => 'nope']);
    }

    #[Test]
    public function aPostProcessorTransformsTheMergedConfig(): void
    {
        $stamp = static fn(array $config): array => [...$config, 'stamped' => true];

        $aggregator = new ConfigAggregator(
            [new ArrayProvider(['named' => true])],
            null,
            [$stamp],
        );

        static::assertSame(['named' => true, 'stamped' => true], $aggregator->getMergedConfig());
    }

    #[Test]
    public function aPreProcessorThatDoesNotReturnAnIterableIsReported(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'A config pre-processor must return an iterable of providers; it returned "string"',
        );

        new ConfigAggregator([new ArrayProvider([])], null, [], [static fn(iterable $p): string => 'nope']);
    }

    #[Test]
    public function aPreProcessorTransformsTheProviderList(): void
    {
        // A pre-processor receives the provider list and returns a replacement, so it can
        // inject providers the caller did not name.
        $appended = static function (iterable $providers): array {
            $list = [];
            foreach ($providers as $provider) {
                $list[] = $provider;
            }

            $list[] = new ArrayProvider(['injected' => true]);

            return $list;
        };

        $aggregator = new ConfigAggregator(
            [new ArrayProvider(['named' => true])],
            null,
            [],
            [$appended],
        );

        static::assertSame(['named' => true, 'injected' => true], $aggregator->getMergedConfig());
    }

    #[Test]
    public function aProcessorClassStringIsInstantiated(): void
    {
        $aggregator = new ConfigAggregator(
            [new ArrayProvider(['named' => true])],
            null,
            [],
            [AddsInjectedProviderPreProcessor::class],
        );

        static::assertSame(['named' => true, 'injected' => true], $aggregator->getMergedConfig());
    }

    #[Test]
    public function iterationYieldsTheMergedValues(): void
    {
        $aggregator = new ConfigAggregator([
            new ArrayProvider(['a' => 1, 'b' => 2]),
        ]);

        $seen = [];

        foreach ($aggregator as $key => $value) {
            $seen[$key] = $value;
        }

        static::assertSame(['a' => 1, 'b' => 2], $seen);
    }

    #[Test]
    public function orderingIsAppliedToProvidersFromAPreProcessor(): void
    {
        // The pre-processor's output is the base order and ordering refines it. WinningProvider
        // is listed FIRST, which is what the installer does to a newly installed package, and
        // it must still win the shared key.
        $installerOrder = static fn(iterable $providers): array => [
            WinningProvider::class,
            BaseKeyProvider::class,
        ];

        $aggregator = new ConfigAggregator([new ArrayProvider([])], null, [], [$installerOrder]);

        static::assertSame(['shared' => 'winner'], $aggregator->getMergedConfig());
    }

    #[Test]
    public function postProcessorsAreTheThirdArgumentAndPreProcessorsTheFourth(): void
    {
        // The aggregator's order is post then pre, which is unintuitive. A caller using
        // positional arguments must not have them swapped.
        $appended = static fn(iterable $providers): array => [new ArrayProvider(['pre' => true])];
        $stamp    = static fn(array $config): array => [...$config, 'post' => true];

        $aggregator = new ConfigAggregator([new ArrayProvider(['named' => true])], null, [$stamp], [$appended]);

        static::assertSame(['pre' => true, 'post' => true], $aggregator->getMergedConfig());
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

    #[Test]
    public function theMergedConfigIsReadableThroughTheConfigAccessors(): void
    {
        // A provider returns an array and a Config consumes one, so the merged result is
        // the configuration itself rather than an array that has to be wrapped again.
        $aggregator = new ConfigAggregator([
            new ArrayProvider(['first' => 'value', 'second' => 2]),
        ]);

        static::assertSame('value', $aggregator->get('first'));
        static::assertSame('fallback', $aggregator->get(
            name   : 'absent',
            default: 'fallback',
        ));
        static::assertSame(2, $aggregator['second']);
        static::assertTrue($aggregator->offsetExists(offset: 'first'));
        static::assertFalse($aggregator->offsetExists(offset: 'absent'));
        static::assertCount(2, $aggregator);
        static::assertSame(
            ['first' => 'value', 'second' => 2],
            $aggregator->toArray(),
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
