<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webware\ConfigManager\Provider\ProviderReference;
use WebwareTest\ConfigManager\Provider\TestAssets\ArrayConfigProvider;

use function spl_object_id;

#[CoversClass(ProviderReference::class)]
final class ProviderReferenceTest extends TestCase
{
    #[Test]
    public function aClassStringIsIdentifiedByItsNameAndLabelledWithIt(): void
    {
        $reference = ProviderReference::of(ArrayConfigProvider::class);

        static::assertSame(ArrayConfigProvider::class, $reference->value);
        static::assertSame('class:' . ArrayConfigProvider::class, $reference->identity);
        static::assertSame(ArrayConfigProvider::class, $reference->label);
    }

    #[Test]
    public function aClosureIsLabelledAsAClosureRatherThanWithItsInternalName(): void
    {
        $reference = ProviderReference::of(static fn(): array => []);

        static::assertSame('Closure', $reference->label);
        static::assertStringStartsWith('object:Closure:', $reference->identity);
    }

    #[Test]
    public function anAnonymousClassIsLabelledWithItsRuntimeClass(): void
    {
        $provider  = new ArrayConfigProvider();
        $reference = ProviderReference::of($provider);

        // The label must be usable in an error message, so it is the class name
        // rather than something derived from the object.
        static::assertSame($provider::class, $reference->label);
    }

    #[Test]
    public function anInstanceIsIdentifiedByObjectHandleSoTwoInstancesAreDistinct(): void
    {
        $provider  = new ArrayConfigProvider();
        $reference = ProviderReference::of($provider);

        static::assertSame(
            'object:' . ArrayConfigProvider::class . ':' . spl_object_id($provider),
            $reference->identity,
        );
        static::assertSame(ArrayConfigProvider::class, $reference->label);
    }

    #[Test]
    public function anObjectThatIsNotAClosureIsLabelledWithItsClass(): void
    {
        $reference = ProviderReference::of(new stdClass());

        static::assertSame(stdClass::class, $reference->label);
        static::assertStringStartsWith('object:' . stdClass::class . ':', $reference->identity);
    }

    #[Test]
    public function aNonStringNonObjectValueIsLabelledWithItsType(): void
    {
        $arrayReference = ProviderReference::of([]);
        static::assertSame('other:array', $arrayReference->identity);
        static::assertSame('array', $arrayReference->label);

        $intReference = ProviderReference::of(7);
        static::assertSame('other:int', $intReference->identity);
        static::assertSame('int', $intReference->label);

        $nullReference = ProviderReference::of(null);
        static::assertSame('other:null', $nullReference->identity);
        static::assertSame('null', $nullReference->label);
    }

    #[Test]
    public function twoInstancesOfOneClassProduceDifferentIdentities(): void
    {
        $first  = ProviderReference::of(new ArrayConfigProvider());
        $second = ProviderReference::of(new ArrayConfigProvider());

        static::assertNotSame($first->identity, $second->identity);
    }
}
