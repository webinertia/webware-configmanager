<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Processor;

use Not\A\Real\Processor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Processor\ProcessorResolver;
use WebwareTest\ConfigManager\Processor\TestAssets\IdentityProcessor;
use WebwareTest\ConfigManager\Processor\TestAssets\InvokableProcessorAsset;
use WebwareTest\ConfigManager\Provider\TestAssets\AbstractConfigProvider;
use WebwareTest\ConfigManager\Provider\TestAssets\RequiresArgumentConfigProvider;

#[CoversClass(ProcessorResolver::class)]
final class ProcessorResolverTest extends TestCase
{
    #[Test]
    public function aCallableIsReturnedVerbatim(): void
    {
        $processor = static fn(array $config): array => $config;

        static::assertSame($processor, new ProcessorResolver()->resolve($processor));
    }

    #[Test]
    public function aClassStringIsInstantiated(): void
    {
        // IdentityProcessor is a ProcessorInterface, not a callable, so this proves the
        // class-string path is resolved and rejected rather than silently accepted.
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A config processor must be a class-string or a callable');

        new ProcessorResolver()->resolve(IdentityProcessor::class);
    }

    #[Test]
    public function aClassStringWhoseConstructorRequiresArgumentsIsReported(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            'Config processor class "'
                . RequiresArgumentConfigProvider::class
                . '" cannot be instantiated without arguments',
        );

        new ProcessorResolver()->resolve(RequiresArgumentConfigProvider::class);
    }

    #[Test]
    public function aMissingClassStringIsReported(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Config processor class "' . Processor::class . '" does not exist');

        new ProcessorResolver()->resolve(Processor::class);
    }

    #[Test]
    public function anAbstractClassStringIsReported(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            'Config processor class "' . AbstractConfigProvider::class . '" cannot be instantiated',
        );

        new ProcessorResolver()->resolve(AbstractConfigProvider::class);
    }

    #[Test]
    public function anInvokableClassStringIsInstantiatedAndReturned(): void
    {
        $resolved = new ProcessorResolver()->resolve(InvokableProcessorAsset::class);

        static::assertSame([], $resolved([]));
    }

    #[Test]
    public function anInvokableInstanceIsReturnedVerbatim(): void
    {
        $processor = new class {
            /** @param array<array-key, mixed> $config @return array<array-key, mixed> */
            public function __invoke(array $config): array
            {
                return $config;
            }
        };

        static::assertSame($processor, new ProcessorResolver()->resolve($processor));
    }

    #[Test]
    public function aValueThatIsNeitherIsReportedWithItsType(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('A config processor must be a class-string or a callable; received "int"');

        new ProcessorResolver()->resolve(42);
    }
}
