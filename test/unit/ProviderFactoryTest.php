<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use ArgumentCountError;
use Error;
use Not\A\Real\Thing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use Webware\ConfigManager\Container\ProviderFactory;
use Webware\ConfigManager\Exception;
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

    #[Test]
    public function invokeBuildsTheRequestedServiceSoItWorksAsAFactoriesValue(): void
    {
        // The service manager resolves a string factory by instantiating it and asking
        // whether it is callable, then invokes it with (container, name, options). Without
        // this path a `factories => [Class => ProviderFactory::class]` entry is a class
        // string that is not callable, and every plugin fails to resolve.
        $factory = new ProviderFactory();

        static::assertSame(
            stdClass::class,
            $factory(
                $this->createStub(ContainerInterface::class),
                stdClass::class,
            )::class,
        );
    }

    #[Test]
    public function invokeReportsAMissingClassAsAPlugin(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Plugin class "' . Thing::class . '" does not exist');

        new ProviderFactory()($this->createStub(ContainerInterface::class), Thing::class);
    }
}
