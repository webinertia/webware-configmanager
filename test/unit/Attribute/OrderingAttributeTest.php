<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Attribute;

use Not\A\Real\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Webware\ConfigManager\Attribute\LoadAfter;
use Webware\ConfigManager\Attribute\LoadBefore;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\DanglingProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\EarlyProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\LateProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\MultiTargetProvider;
use WebwareTest\ConfigManager\Provider\OrderTestAssets\PlainProvider;

use function array_map;

#[CoversClass(LoadAfter::class)]
#[CoversClass(LoadBefore::class)]
final class OrderingAttributeTest extends TestCase
{
    /**
     * @param class-string $class
     * @param class-string $attribute
     * @return list<LoadAfter|LoadBefore>
     * @throws ReflectionException
     */
    private static function instances(string $class, string $attribute): array
    {
        return array_map(
            static fn(ReflectionAttribute $reflection) => $reflection->newInstance(),
            new ReflectionClass($class)->getAttributes($attribute),
        );
    }

    #[Test]
    public function afterAndBeforeAreReadIndependently(): void
    {
        static::assertCount(1, self::instances(MultiTargetProvider::class, LoadBefore::class));
        static::assertSame(
            [LateProvider::class],
            self::instances(MultiTargetProvider::class, LoadBefore::class)[0]->providers,
        );
    }

    #[Test]
    public function aTargetThatDoesNotExistIsStillDeclared(): void
    {
        // Nothing can be resolved at read time, because a package cannot know which of its
        // peers the application installed.
        static::assertSame(
            [Provider::class],
            self::instances(DanglingProvider::class, LoadAfter::class)[0]->providers,
        );
    }

    #[Test]
    public function aVariadicArgumentBecomesAListOfTargets(): void
    {
        $attributes = self::instances(MultiTargetProvider::class, LoadAfter::class);

        static::assertCount(1, $attributes);
        static::assertSame(
            [
                EarlyProvider::class,
                PlainProvider::class,
            ],
            $attributes[0]->providers,
        );
    }
}
