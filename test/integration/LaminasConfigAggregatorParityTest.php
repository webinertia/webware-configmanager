<?php

declare(strict_types=1);

namespace WebwareTestIntegration\ConfigManager;

use Laminas\ConfigAggregator\ConfigAggregator as LaminasConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider as LaminasPhpFileProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\ConfigAggregator;
use Webware\ConfigManager\Merge\ConfigMerger;
use Webware\ConfigManager\Provider\ConfigProviderResolver;
use Webware\ConfigManager\Provider\PhpFileProvider;
use WebwareTest\ConfigManager\Provider\TestAssets\ArrayConfigProvider;

/**
 * Compares this package's aggregation against the aggregator it replaces.
 *
 * `ConfigAggregator` is presented as a drop-in for `Laminas\ConfigAggregator\ConfigAggregator`,
 * so "drop-in" is a claim that can be measured: run the same provider list through both and
 * the merged arrays must be identical. Anything this package adds is additive by
 * construction (ordering declarations, merge directives), and a provider list that uses
 * neither has to behave exactly as the upstream one does.
 *
 * The comparison is against the installed upstream package rather than a checked-in
 * expected array, so it cannot drift out of date unnoticed.
 */
#[CoversClass(ConfigAggregator::class)]
#[CoversClass(ConfigProviderResolver::class)]
#[CoversClass(ConfigMerger::class)]
final class LaminasConfigAggregatorParityTest extends TestCase
{
    /** @return iterable<string, array{list<class-string|callable>, list<class-string|callable>, callable}> */
    public static function postProcessedProviderLists(): iterable
    {
        $providers  = [new ArrayConfigProvider(['shared' => ['a' => 1]])];
        $append     = static fn(array $config): array => ['post-processed' => true] + $config;
        $overriding = static fn(array $config): array => ['shared' => 'replaced'];

        yield 'a post-processor adds a key' => [$providers, $providers, $append];
        yield 'a post-processor replaces everything' => [$providers, $providers, $overriding];
    }

    /** @return iterable<string, array{list<class-string|callable>, list<class-string|callable>}> */
    public static function providerLists(): iterable
    {
        $overlapping = [
            new ArrayConfigProvider(['shared' => ['a' => 1, 'b' => 2], 'only-first' => true]),
            new ArrayConfigProvider(['shared' => ['b' => 3], 'only-second' => true]),
        ];
        $listValues = [
            new ArrayConfigProvider(['handlers' => ['first']]),
            new ArrayConfigProvider(['handlers' => ['second']]),
        ];

        yield 'one provider' => [[$overlapping[0]], [$overlapping[0]]];
        yield 'overlapping nested keys' => [$overlapping, $overlapping];
        yield 'a list valued key is appended' => [$listValues, $listValues];

        // The two file providers are separate classes with the same constructor, so this is
        // the case that proves ours is a substitute rather than merely a look-alike.
        $pattern = __DIR__ . '/../unit/TestAssets/Php/include-base*.php';

        yield 'php file providers' => [
            [new PhpFileProvider($pattern)],
            [new LaminasPhpFileProvider($pattern)],
        ];
    }

    #[Test]
    public function anEmptyProviderListAggregatesToAnEmptyConfig(): void
    {
        static::assertSame([], new ConfigAggregator([])->getMergedConfig());
    }

    /**
     * @param list<class-string|callable> $ours
     * @param list<class-string|callable> $upstream
     */
    #[Test]
    #[DataProvider('postProcessedProviderLists')]
    public function postProcessorsProduceTheSameResultAsTheUpstreamAggregators(
        array $ours,
        array $upstream,
        callable $processor,
    ): void {
        static::assertSame(
            new LaminasConfigAggregator($upstream, null, [$processor])->getMergedConfig(),
            new ConfigAggregator($ours, null, [$processor])->getMergedConfig(),
        );
    }

    /**
     * @param list<class-string|callable> $ours
     * @param list<class-string|callable> $upstream
     */
    #[Test]
    #[DataProvider('providerLists')]
    public function theMergedConfigIsIdenticalToTheUpstreamAggregators(
        array $ours,
        array $upstream,
    ): void {
        $expected = new LaminasConfigAggregator($upstream)->getMergedConfig();

        // A list that stopped producing anything would make the comparison vacuous, and a
        // stale glob behind the php-file case is exactly how that would happen.
        static::assertNotEmpty($expected);
        static::assertSame($expected, new ConfigAggregator($ours)->getMergedConfig());
    }
}
