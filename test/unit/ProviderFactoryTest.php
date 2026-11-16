<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use ArgumentCountError;
use Error;
use Not\A\Real\Thing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\ProviderFactory;
use WebwareTest\ConfigManager\Provider\TestAssets\AbstractConfigProvider;
use WebwareTest\ConfigManager\Provider\TestAssets\ArrayConfigProvider;
use WebwareTest\ConfigManager\Provider\TestAssets\RequiresArgumentConfigProvider;

#[CoversClass(ProviderFactory::class)]
final class ProviderFactoryTest extends TestCase
{
    #[Test]
    public function aClassWithAnOptionalConstructorArgumentIsBuilt(): void
    {
        // The constructor exists but requires nothing, which must not be mistaken for a
        // class that cannot be built without arguments.
        static::assertInstanceOf(
            ArrayConfigProvider::class,
            ProviderFactory::create(
                class: ArrayConfigProvider::class,
                label: 'Config provider',
            ),
        );
    }

    #[Test]
    public function aClassWithNoConstructorIsBuilt(): void
    {
        static::assertInstanceOf(stdClass::class, ProviderFactory::create(
            class: stdClass::class,
            label: 'Thing',
        ));
    }

    #[Test]
    public function aMissingClassIsReportedWithTheCallersLabel(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Config provider class "' . Thing::class . '" does not exist');

        ProviderFactory::create(
            class: Thing::class,
            label: 'Config provider',
        );
    }

    #[Test]
    public function anAbstractClassRaisesAnError(): void
    {
        // Not recovered from: nothing here can choose a concrete subclass, so PHP's own
        // error is the honest report.
        $this->expectException(Error::class);

        ProviderFactory::create(
            class: AbstractConfigProvider::class,
            label: 'Config processor',
        );
    }

    #[Test]
    public function aRequiredConstructorArgumentRaisesAnArgumentCountError(): void
    {
        // A provider that needs its dependencies injected cannot be built at merge time,
        // because nothing in this path holds a container. ArgumentCountError names the
        // class, the constructor and the count, so it is not caught and reworded.
        $this->expectException(ArgumentCountError::class);

        ProviderFactory::create(
            class: RequiresArgumentConfigProvider::class,
            label: 'Config provider',
        );
    }
}
