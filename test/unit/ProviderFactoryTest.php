<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

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

use function sprintf;

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
    public function anAbstractClassIsReportedRatherThanRaisingAnError(): void
    {
        // newInstance() on an abstract class raises Error, so the check has to come first.
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Config processor class "%s" cannot be instantiated: it is abstract or its '
                    . 'constructor is not public, so supply an instance instead',
                AbstractConfigProvider::class,
            ),
        );

        ProviderFactory::create(
            class: AbstractConfigProvider::class,
            label: 'Config processor',
        );
    }

    #[Test]
    public function aRequiredConstructorArgumentIsReportedWithItsCount(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Config provider class "%s" cannot be instantiated without arguments: '
                    . 'its constructor requires 1',
                RequiresArgumentConfigProvider::class,
            ),
        );

        ProviderFactory::create(
            class: RequiresArgumentConfigProvider::class,
            label: 'Config provider',
        );
    }
}
