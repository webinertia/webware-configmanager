<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Provider\ConfigProviderResolver;
use Webware\ConfigManager\Provider\Declaration;
use Webware\ConfigManager\Provider\DeclarationReader;
use Webware\ConfigManager\Provider\OrderGraph;
use Webware\ConfigManager\Provider\PositionIndex;
use Webware\ConfigManager\Provider\ProviderOrderResolver;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\BaseKeyProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\CycleAProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\CycleBProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\EarlyProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\LateProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\MultiTargetProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\OtherPlainProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\PlainProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\WinningProvider;

use function array_values;

/**
 * Every input order of a fully constrained provider set has to resolve to one output order.
 *
 * The risk this covers is the one the feature exists for: `laminas-component-installer`
 * prepends what it writes, so the list a host application ends up with bears no relation
 * to the order its packages intend. A declaration set that fully orders its members must
 * therefore produce the same result for every permutation of that input, and
 * {@see ProviderOrderResolverTest} only ever exercises one or two of them.
 *
 * The sets below are fully constrained on purpose. A provider that declares nothing keeps
 * whatever position it is given, so it belongs in a stability test rather than here.
 */
#[CoversClass(ProviderOrderResolver::class)]
#[CoversClass(DeclarationReader::class)]
#[CoversClass(Declaration::class)]
#[CoversClass(OrderGraph::class)]
#[CoversClass(PositionIndex::class)]
final class ProviderOrderPermutationTest extends TestCase
{
    /** @return iterable<string, array{list<class-string>, list<class-string>}> */
    public static function permutationsOfConstrainedSets(): iterable
    {
        $sets = [
            // LoadBefore one way, LoadAfter the other, the target in the middle.
            'before and after one target' => [
                [EarlyProvider::class, PlainProvider::class, LateProvider::class],
                [EarlyProvider::class, PlainProvider::class, LateProvider::class],
            ],
            // Two attributes on one class, one of them variadic.
            'variadic arguments with two attributes' => [
                [MultiTargetProvider::class, EarlyProvider::class, PlainProvider::class,       LateProvider::class],
                [EarlyProvider::class,       PlainProvider::class, MultiTargetProvider::class, LateProvider::class],
            ],
            // The installer scenario: the overriding package is listed first.
            'an override listed before its base' => [
                [WinningProvider::class, BaseKeyProvider::class],
                [BaseKeyProvider::class, WinningProvider::class],
            ],
        ];

        foreach ($sets as $name => [$list, $expected]) {
            foreach (self::permutations($list) as $index => $permutation) {
                yield "{$name}, input {$index}" => [$permutation, $expected];
            }
        }
    }

    /**
     * @param list<class-string> $items
     * @return iterable<int, list<class-string>>
     */
    private static function permutations(array $items): iterable
    {
        if ([] === $items) {
            yield [];

            return;
        }

        foreach ($items as $index => $item) {
            $rest = $items;
            unset($rest[$index]);

            foreach (self::permutations(array_values($rest)) as $permutation) {
                yield [$item, ...$permutation];
            }
        }
    }

    #[Test]
    public function aCycleIsReportedByAggregation(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);

        new ConfigProviderResolver()->aggregate([CycleAProvider::class, CycleBProvider::class]);
    }

    /**
     * @param list<class-string> $input
     * @param list<class-string> $expected
     */
    #[Test]
    #[DataProvider('permutationsOfConstrainedSets')]
    public function aFullyConstrainedSetResolvesToTheSameOrderWhateverTheInputOrder(
        array $input,
        array $expected,
    ): void {
        static::assertSame($expected, new ProviderOrderResolver()->order($input));
    }

    #[Test]
    public function anUnconstrainedProviderKeepsItsPositionWhileAConstraintResolves(): void
    {
        // Stability is the whole reason this can be applied unconditionally: an entry with
        // no declaration is never moved ahead of one that precedes it, and the constrained
        // pair still resolves around it.
        static::assertSame(
            [OtherPlainProvider::class, EarlyProvider::class, PlainProvider::class],
            new ProviderOrderResolver()->order([
                OtherPlainProvider::class,
                PlainProvider::class,
                EarlyProvider::class,
            ]),
        );
    }

    #[Test]
    public function theOrderingIsAppliedByAggregationNotOnlyByTheResolver(): void
    {
        // Reads as "the resolver call is still in the aggregation path". Without it the
        // overriding provider is listed first and loses the key, which is the bug the
        // declarations exist to prevent.
        $forward = new ConfigProviderResolver()->aggregate([
            WinningProvider::class,
            BaseKeyProvider::class,
        ]);
        $reverse = new ConfigProviderResolver()->aggregate([
            BaseKeyProvider::class,
            WinningProvider::class,
        ]);

        static::assertSame(['shared' => 'winner'], $forward);
        static::assertSame($forward, $reverse);
    }
}
