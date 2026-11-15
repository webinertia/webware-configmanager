<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Webware\ConfigManager\Container\ConfigService;

use function array_key_exists;

#[CoversClass(ConfigService::class)]
final class ConfigServiceTest extends TestCase
{
    /** @param array<string, mixed> $services */
    private static function containerWith(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /** @param array<string, mixed> $services */
            public function __construct(
                private readonly array $services,
            ) {}

            public function get(string $id): mixed
            {
                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }

    #[Test]
    public function itLetsAContainerErrorPropagate(): void
    {
        // A container that throws is a broken container, not a missing config, so the
        // failure must not be swallowed here.
        $container = new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new RuntimeException('container is broken');
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('container is broken');

        ConfigService::entriesOf($container);
    }

    #[Test]
    public function itReturnsAnEmptyArrayWhenTheConfigServiceIsAbsent(): void
    {
        static::assertSame([], ConfigService::entriesOf(self::containerWith([])));
    }

    #[Test]
    public function itReturnsAnEmptyArrayWhenTheConfigServiceIsNotAnArray(): void
    {
        static::assertSame([], ConfigService::entriesOf(self::containerWith(['config' => 'not-an-array'])));
        static::assertSame([], ConfigService::entriesOf(self::containerWith(['config' => null])));
    }

    #[Test]
    public function itReturnsTheConfigEntriesWhenTheyAreAnArray(): void
    {
        $container = self::containerWith(['config' => ['db' => ['host' => 'localhost']]]);

        static::assertSame(['db' => ['host' => 'localhost']], ConfigService::entriesOf($container));
    }

    #[Test]
    public function theReturnedArrayPreservesKeysAndNullValues(): void
    {
        // The reader reports what is there. Deciding that null means absent belongs to
        // the caller, which in this package is AbstractConfigFactory.
        $entries = ConfigService::entriesOf(self::containerWith(['config' => ['a' => null, 3 => 'three']]));

        static::assertTrue(array_key_exists('a', $entries));
        static::assertNull($entries['a']);
        static::assertSame('three', $entries[3]);
    }
}
