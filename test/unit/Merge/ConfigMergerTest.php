<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Merge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Merge\ConfigMerger;

#[CoversClass(ConfigMerger::class)]
class ConfigMergerTest extends TestCase
{
    #[Test]
    public function testMergeAppendsRatherThanReplacingAtTheSameIndex(): void
    {
        // The override's index 0 is a new entry, not a replacement, which is the
        // behaviour config aggregation depends on.
        static::assertSame(
            ['a', 'b'],
            ConfigMerger::merge(['a'], ['b']),
        );
    }

    #[Test]
    public function testMergeAppendsValuesUnderAnIntegerKeyPresentInBoth(): void
    {
        static::assertSame(
            ['handlers' => ['first', 'second']],
            ConfigMerger::merge(['handlers' => ['first']], ['handlers' => ['second']]),
        );
    }

    #[Test]
    public function testMergeAppendsWhenAnAppendFillsTheNextIndex(): void
    {
        // The second file declares index 1, but index 1 is created by appending the
        // value at index 0, so the check sees a collision and appends again.
        static::assertSame(
            ['router' => 'value', 'a', 'a', 'b'],
            ConfigMerger::merge(['router' => 'value', 'a'], ['router' => 'value', 'a', 'b']),
        );
    }

    #[Test]
    public function testMergeAssignsIntegerKeysThatOnlyTheOverrideDeclares(): void
    {
        static::assertSame(
            ['router' => 'value', 'a', 'b'],
            ConfigMerger::merge(['router' => 'value'], ['router' => 'value', 'a', 'b']),
        );
    }

    #[Test]
    public function testMergeCanOverwriteAValueWithNull(): void
    {
        static::assertSame(
            ['key' => null],
            ConfigMerger::merge(['key' => 'base'], ['key' => null]),
        );
    }

    #[Test]
    public function testMergeCopiesKeysThatOnlyTheOverrideDeclares(): void
    {
        static::assertSame(
            ['kept' => 'base', 'added' => 'override'],
            ConfigMerger::merge(['kept' => 'base'], ['added' => 'override']),
        );
    }

    #[Test]
    public function testMergeIgnoresAnEmptyBase(): void
    {
        static::assertSame(
            ['one' => 1, 'two' => ['a' => 'b']],
            ConfigMerger::merge([], ['one' => 1, 'two' => ['a' => 'b']]),
        );
    }

    #[Test]
    public function testMergeIgnoresAnEmptyOverride(): void
    {
        static::assertSame(
            ['one' => 1, 'two' => ['a' => 'b']],
            ConfigMerger::merge(['one' => 1, 'two' => ['a' => 'b']], []),
        );
    }

    #[Test]
    public function testMergeKeepsBaseOrderAndAppendsNewKeys(): void
    {
        static::assertSame(
            ['first' => 1, 'second' => 2, 'third' => 3],
            ConfigMerger::merge(['first' => 1, 'second' => 2], ['third' => 3]),
        );
    }

    #[Test]
    public function testMergeRecursesThroughSeveralLevels(): void
    {
        $base     = ['a' => ['b' => ['c' => ['d' => 'base', 'keep' => true]]]];
        $override = ['a' => ['b' => ['c' => ['d' => 'override']]]];

        static::assertSame(
            ['a' => ['b' => ['c' => ['d' => 'override', 'keep' => true]]]],
            ConfigMerger::merge($base, $override),
        );
    }

    #[Test]
    public function testMergeRecursivelyMergesArraysUnderTheSameStringKey(): void
    {
        $base     = ['db' => ['host' => 'localhost', 'port' => 5432]];
        $override = ['db' => ['port' => 3306, 'name' => 'app']];

        static::assertSame(
            ['db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'app']],
            ConfigMerger::merge($base, $override),
        );
    }

    #[Test]
    public function testMergeReplacesAnArrayWithAScalar(): void
    {
        static::assertSame(
            ['key' => 'scalar'],
            ConfigMerger::merge(['key' => ['nested' => true]], ['key' => 'scalar']),
        );
    }

    #[Test]
    public function testMergeReplacesAScalarWithAnArray(): void
    {
        static::assertSame(
            ['key' => ['nested' => true]],
            ConfigMerger::merge(['key' => 'scalar'], ['key' => ['nested' => true]]),
        );
    }

    #[Test]
    public function testMergeReplacesAScalarWithAScalar(): void
    {
        static::assertSame(
            ['key' => 'override'],
            ConfigMerger::merge(['key' => 'base'], ['key' => 'override']),
        );
    }

    #[Test]
    public function testMergeTreatsANullBaseValueAsPresent(): void
    {
        static::assertSame(
            ['key' => 'override'],
            ConfigMerger::merge(['key' => null], ['key' => 'override']),
        );
    }
}
