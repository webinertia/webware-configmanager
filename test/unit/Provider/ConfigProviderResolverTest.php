<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider;

use Not\A\Real\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Merge\MergeDirective;
use Webware\ConfigManager\Provider\ConfigProviderResolver;
use WebwareTest\ConfigManager\Provider\TestAssets\ArrayConfigProvider;
use WebwareTest\ConfigManager\Provider\TestAssets\NotAnArrayConfigProvider;
use WebwareTest\ConfigManager\Provider\TestAssets\RequiresArgumentConfigProvider;

#[CoversClass(ConfigProviderResolver::class)]
final class ConfigProviderResolverTest extends TestCase
{
    #[Test]
    public function aClassStringProviderIsInstantiatedAndResolved(): void
    {
        $resolved = new ConfigProviderResolver()->resolve([ArrayConfigProvider::class]);

        static::assertCount(1, $resolved);
        static::assertInstanceOf(ArrayConfigProvider::class, $resolved[0]);
        static::assertSame([], $resolved[0]());
    }

    #[Test]
    public function aClassThatCannotBeInstantiatedWithoutArgumentsIsRejected(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            'Config provider class "'
                . RequiresArgumentConfigProvider::class
                . '" cannot be instantiated without arguments',
        );

        new ConfigProviderResolver()->resolve([RequiresArgumentConfigProvider::class]);
    }

    #[Test]
    public function aClassWithoutAnInvokeMethodIsRejected(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Config provider must be a class-string or a callable; received "stdClass"');

        new ConfigProviderResolver()->resolve([stdClass::class]);
    }

    #[Test]
    public function aClosureIsNamedAsAClosureInAnErrorMessage(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Config provider "Closure" does not return an array; received "string"');

        new ConfigProviderResolver()->aggregate([static fn(): string => 'nope']);
    }

    #[Test]
    public function aClosureIsSupported(): void
    {
        $closure = static fn(): array => ['foo' => 'bar'];

        static::assertSame([$closure], new ConfigProviderResolver()->resolve([$closure]));
    }

    #[Test]
    public function aggregateHonoursAMergeDirectiveInsideProviderOutput(): void
    {
        $config = new ConfigProviderResolver()->aggregate([
            new ArrayConfigProvider(['db' => ['host' => 'localhost', 'port' => 5432], 'cache' => ['ttl' => 60]]),
            new ArrayConfigProvider([
                'db'    => MergeDirective::replace(['host' => 'remote', 'port' => 5433]),
                'cache' => MergeDirective::remove(),
            ]),
        ]);

        static::assertSame(['db' => ['host' => 'remote', 'port' => 5433]], $config);
    }

    #[Test]
    public function aggregateMergesInOrderSoALaterProviderWins(): void
    {
        $config = new ConfigProviderResolver()->aggregate([
            new ArrayConfigProvider(['shared' => ['a' => 1, 'b' => 2], 'only-first' => true]),
            new ArrayConfigProvider(['shared' => ['b' => 3], 'only-second' => true]),
        ]);

        static::assertSame(
            [
                'shared'      => ['a' => 1, 'b' => 3],
                'only-first'  => true,
                'only-second' => true,
            ],
            $config,
        );
    }

    #[Test]
    public function aggregateRejectsAProviderThatDoesNotReturnAnArray(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'Config provider "'
                . NotAnArrayConfigProvider::class
                . '" does not return an array; received "string"',
        );

        new ConfigProviderResolver()->aggregate([NotAnArrayConfigProvider::class]);
    }

    #[Test]
    public function anEmptyProviderListAggregatesToAnEmptyConfig(): void
    {
        static::assertSame([], new ConfigProviderResolver()->resolve([]));
        static::assertSame([], new ConfigProviderResolver()->aggregate([]));
    }

    #[Test]
    public function anInstanceProviderIsUsedVerbatim(): void
    {
        $provider = new ArrayConfigProvider(['foo' => 'bar']);

        $resolved = new ConfigProviderResolver()->resolve([$provider]);

        static::assertSame([$provider], $resolved);
    }

    #[Test]
    public function anInvalidReferenceIsReportedWithItsType(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Config provider must be a class-string or a callable; received "array"');

        new ConfigProviderResolver()->resolve([[]]);
    }

    #[Test]
    public function anUnknownClassStringIsRejected(): void
    {
        // The class deliberately does not exist; ::class still produces its name.
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Config provider class "' . Provider::class . '" does not exist');

        new ConfigProviderResolver()->resolve([Provider::class]);
    }

    #[Test]
    public function aProviderSuppliedTwiceIsRejected(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'Config provider "' . ArrayConfigProvider::class . '" was supplied more than once',
        );

        new ConfigProviderResolver()->resolve([ArrayConfigProvider::class, ArrayConfigProvider::class]);
    }

    #[Test]
    public function twoInstancesOfOneClassAreNotMistakenForDuplicates(): void
    {
        $first  = new ArrayConfigProvider(['a' => 1]);
        $second = new ArrayConfigProvider(['b' => 2]);

        static::assertCount(2, new ConfigProviderResolver()->resolve([$first, $second]));
    }
}
