<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Provider\ArrayProvider;

#[CoversClass(ArrayProvider::class)]
final class ArrayProviderTest extends TestCase
{
    #[Test]
    public function anEmptyConfigIsReturnedAsAnEmptyArray(): void
    {
        static::assertSame([], new ArrayProvider([])());
    }

    #[Test]
    public function theSeededConfigIsReturnedUnchanged(): void
    {
        $config = ['config_cache_enabled' => false, 'nested' => ['a' => 1]];

        static::assertSame($config, new ArrayProvider($config)());
    }
}
