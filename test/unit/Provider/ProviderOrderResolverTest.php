<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider;

use Not\A\Real\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Provider\Declaration;
use Webware\ConfigManager\Provider\DeclarationReader;
use Webware\ConfigManager\Provider\OrderGraph;
use Webware\ConfigManager\Provider\PositionIndex;
use Webware\ConfigManager\Provider\ProviderOrderResolver;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\ConfigProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\CycleAProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\CycleBProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\DanglingProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\EarlyProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\LateProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\MultiTargetProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\OtherPlainProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\PlainProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\SelfBeforeProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\SelfReferencingProvider;

use function is_callable;

#[CoversClass(ProviderOrderResolver::class)]
#[CoversClass(DeclarationReader::class)]
#[CoversClass(Declaration::class)]
#[CoversClass(OrderGraph::class)]
#[CoversClass(PositionIndex::class)]
final class ProviderOrderResolverTest extends TestCase
{
    #[Test]
    public function aCycleIsReportedWithItsParticipants(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'Config provider ordering declarations form a cycle between: '
                . CycleAProvider::class
                . ', '
                . CycleBProvider::class,
        );

        new ProviderOrderResolver()->order([CycleAProvider::class, CycleBProvider::class]);
    }

    #[Test]
    public function aDanglingTargetIsIgnored(): void
    {
        static::assertSame(
            [DanglingProvider::class, PlainProvider::class],
            new ProviderOrderResolver()->order([DanglingProvider::class, PlainProvider::class]),
        );
    }

    #[Test]
    public function aListWithNoDeclarationsKeepsTheCallersOrder(): void
    {
        // This is what makes the behaviour safe to apply unconditionally: nothing moves.
        static::assertSame(
            [OtherPlainProvider::class, PlainProvider::class],
            new ProviderOrderResolver()->order([OtherPlainProvider::class, PlainProvider::class]),
        );
    }

    #[Test]
    public function anEmptyListIsReturnedAsGiven(): void
    {
        static::assertSame([], new ProviderOrderResolver()->order([]));
    }

    #[Test]
    public function anInstanceIsOrderedByItsClass(): void
    {
        // A package supplying an instance still declares its position.
        $instance = new ConfigProvider(['value' => 'instance']);

        $ordered = new ProviderOrderResolver()->order([$instance, PlainProvider::class]);

        static::assertSame([PlainProvider::class, $instance], $ordered);
    }

    #[Test]
    public function aReferenceThatIsNotAClassOrObjectDeclaresNothing(): void
    {
        // DeclarationReader is total because it runs before the resolver rejects the entry,
        // so a malformed reference has to read as "no declarations" rather than blow up.
        static::assertSame([], DeclarationReader::read(42));
    }

    #[Test]
    public function aReferenceToAMissingClassDeclaresNothing(): void
    {
        static::assertSame([], DeclarationReader::read(Provider::class));
    }

    #[Test]
    public function aSelfReferenceInLoadBeforeIsNotACycle(): void
    {
        static::assertSame(
            [SelfBeforeProvider::class, PlainProvider::class],
            new ProviderOrderResolver()->order([SelfBeforeProvider::class, PlainProvider::class]),
        );
    }

    #[Test]
    public function aSelfReferenceIsNotACycle(): void
    {
        // Two entries, so the single-provider shortcut does not mask the self-reference.
        static::assertSame(
            [SelfReferencingProvider::class, PlainProvider::class],
            new ProviderOrderResolver()->order([SelfReferencingProvider::class, PlainProvider::class]),
        );
    }

    #[Test]
    public function aSingleProviderIsReturnedAsGiven(): void
    {
        static::assertSame([PlainProvider::class], new ProviderOrderResolver()->order([PlainProvider::class]));
    }

    #[Test]
    public function aVariadicDeclarationConstrainsEveryTarget(): void
    {
        $ordered = new ProviderOrderResolver()->order([
            MultiTargetProvider::class,
            LateProvider::class,
            PlainProvider::class,
            EarlyProvider::class,
        ]);

        static::assertSame(
            [
                EarlyProvider::class,
                PlainProvider::class,
                MultiTargetProvider::class,
                LateProvider::class,
            ],
            $ordered,
        );
    }

    #[Test]
    public function loadAfterMovesTheDeclaringProviderLater(): void
    {
        // The installer prepends a new package, which is the exact case being fixed.
        static::assertSame(
            [PlainProvider::class, LateProvider::class],
            new ProviderOrderResolver()->order([LateProvider::class, PlainProvider::class]),
        );
    }

    #[Test]
    public function loadBeforeMovesTheDeclaringProviderEarlier(): void
    {
        static::assertSame(
            [EarlyProvider::class, PlainProvider::class],
            new ProviderOrderResolver()->order([PlainProvider::class, EarlyProvider::class]),
        );
    }

    #[Test]
    public function theResolverIsInvokableSoItCanBeAPreProcessor(): void
    {
        // __invoke has the shape callable(iterable): iterable, which is the aggregator's
        // pre-processor contract.
        $resolver = new ProviderOrderResolver();

        static::assertTrue(is_callable($resolver));
        static::assertSame(
            [PlainProvider::class, LateProvider::class],
            $resolver([LateProvider::class, PlainProvider::class]),
        );
    }

    #[Test]
    public function unconstrainedProvidersKeepTheirRelativeOrder(): void
    {
        // Early must precede Plain, Plain must precede Late, and the unconstrained
        // provider at the end stays at the end because nothing requires it to move.
        $last = new OtherPlainProvider();

        static::assertSame(
            [EarlyProvider::class, PlainProvider::class, LateProvider::class, $last],
            new ProviderOrderResolver()->order([
                LateProvider::class,
                PlainProvider::class,
                EarlyProvider::class,
                $last,
            ]),
        );
    }
}
