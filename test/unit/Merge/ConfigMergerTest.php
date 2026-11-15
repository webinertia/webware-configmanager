<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Merge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Merge\ConfigMerger;
use Webware\ConfigManager\Merge\MergeDirective;

#[CoversClass(ConfigMerger::class)]
#[CoversClass(MergeDirective::class)]
class ConfigMergerTest extends TestCase
{
    #[Test]
    public function mergeAppendsRatherThanReplacingAtTheSameIndex(): void
    {
        // The override's index 0 is a new entry, not a replacement, which is the
        // behaviour config aggregation depends on.
        static::assertSame(
            ['a', 'b'],
            ConfigMerger::merge(['a'], ['b']),
        );
    }

    #[Test]
    public function mergeAppendsValuesUnderAnIntegerKeyPresentInBoth(): void
    {
        static::assertSame(
            ['handlers' => ['first', 'second']],
            ConfigMerger::merge(['handlers' => ['first']], ['handlers' => ['second']]),
        );
    }

    #[Test]
    public function mergeAppendsWhenAnAppendFillsTheNextIndex(): void
    {
        // The second file declares index 1, but index 1 is created by appending the
        // value at index 0, so the check sees a collision and appends again.
        static::assertSame(
            ['router' => 'value', 'a', 'a', 'b'],
            ConfigMerger::merge(['router' => 'value', 'a'], ['router' => 'value', 'a', 'b']),
        );
    }

    #[Test]
    public function mergeAssignsIntegerKeysThatOnlyTheOverrideDeclares(): void
    {
        static::assertSame(
            ['router' => 'value', 'a', 'b'],
            ConfigMerger::merge(['router' => 'value'], ['router' => 'value', 'a', 'b']),
        );
    }

    #[Test]
    public function mergeCanOverwriteAValueWithNull(): void
    {
        static::assertSame(
            ['key' => null],
            ConfigMerger::merge(['key' => 'base'], ['key' => null]),
        );
    }

    #[Test]
    public function mergeCopiesKeysThatOnlyTheOverrideDeclares(): void
    {
        static::assertSame(
            ['kept' => 'base', 'added' => 'override'],
            ConfigMerger::merge(['kept' => 'base'], ['added' => 'override']),
        );
    }

    #[Test]
    public function mergeIgnoresAnEmptyBase(): void
    {
        static::assertSame(
            ['one' => 1, 'two' => ['a' => 'b']],
            ConfigMerger::merge([], ['one' => 1, 'two' => ['a' => 'b']]),
        );
    }

    #[Test]
    public function mergeIgnoresAnEmptyOverride(): void
    {
        static::assertSame(
            ['one' => 1, 'two' => ['a' => 'b']],
            ConfigMerger::merge(['one' => 1, 'two' => ['a' => 'b']], []),
        );
    }

    #[Test]
    public function mergeKeepsBaseOrderAndAppendsNewKeys(): void
    {
        static::assertSame(
            ['first' => 1, 'second' => 2, 'third' => 3],
            ConfigMerger::merge(['first' => 1, 'second' => 2], ['third' => 3]),
        );
    }

    #[Test]
    public function mergeRecursesThroughSeveralLevels(): void
    {
        $base     = ['a' => ['b' => ['c' => ['d' => 'base', 'keep' => true]]]];
        $override = ['a' => ['b' => ['c' => ['d' => 'override']]]];

        static::assertSame(
            ['a' => ['b' => ['c' => ['d' => 'override', 'keep' => true]]]],
            ConfigMerger::merge($base, $override),
        );
    }

    #[Test]
    public function mergeRecursivelyMergesArraysUnderTheSameStringKey(): void
    {
        $base     = ['db' => ['host' => 'localhost', 'port' => 5432]];
        $override = ['db' => ['port' => 3306, 'name' => 'app']];

        static::assertSame(
            ['db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'app']],
            ConfigMerger::merge($base, $override),
        );
    }

    #[Test]
    public function mergeReplacesAnArrayWithAScalar(): void
    {
        static::assertSame(
            ['key' => 'scalar'],
            ConfigMerger::merge(['key' => ['nested' => true]], ['key' => 'scalar']),
        );
    }

    #[Test]
    public function mergeReplacesAScalarWithAnArray(): void
    {
        static::assertSame(
            ['key' => ['nested' => true]],
            ConfigMerger::merge(['key' => 'scalar'], ['key' => ['nested' => true]]),
        );
    }

    #[Test]
    public function mergeReplacesAScalarWithAScalar(): void
    {
        static::assertSame(
            ['key' => 'override'],
            ConfigMerger::merge(['key' => 'base'], ['key' => 'override']),
        );
    }

    #[Test]
    public function mergeTreatsANullBaseValueAsPresent(): void
    {
        static::assertSame(
            ['key' => 'override'],
            ConfigMerger::merge(['key' => null], ['key' => 'override']),
        );
    }

    #[Test]
    public function removeDirectiveAgainstAnAbsentKeyIsNotStored(): void
    {
        static::assertSame(
            ['kept' => 1],
            ConfigMerger::merge(['kept' => 1], ['absent' => MergeDirective::remove()]),
        );
    }

    #[Test]
    public function removeDirectiveAppliesNested(): void
    {
        static::assertSame(
            ['section' => ['kept' => 2]],
            ConfigMerger::merge(
                ['section' => ['dropped' => 1, 'kept' => 2]],
                ['section' => ['dropped' => MergeDirective::remove()]],
            ),
        );
    }

    #[Test]
    public function removeDirectiveDropsAKeyHoldingNull(): void
    {
        static::assertSame(
            [],
            ConfigMerger::merge(['key' => null], ['key' => MergeDirective::remove()]),
        );
    }

    #[Test]
    public function removeDirectiveDropsAnExistingKey(): void
    {
        static::assertSame(
            ['kept' => 1, 'also' => 3],
            ConfigMerger::merge(
                ['kept' => 1, 'dropped' => 2, 'also' => 3],
                ['dropped' => MergeDirective::remove()],
            ),
        );
    }

    #[Test]
    public function removeDirectiveIsCheckedBeforeTheIntegerKeyAppendRule(): void
    {
        static::assertSame(
            ['handlers' => [1 => 'second']],
            ConfigMerger::merge(
                ['handlers' => ['first', 'second']],
                ['handlers' => [0 => MergeDirective::remove()]],
            ),
        );
    }

    #[Test]
    public function replaceDirectiveAppliesToAnAbsentKey(): void
    {
        static::assertSame(
            ['added' => 9],
            ConfigMerger::merge([], ['added' => MergeDirective::replace(9)]),
        );
    }

    #[Test]
    public function replaceDirectiveIsCheckedBeforeTheIntegerKeyAppendRule(): void
    {
        static::assertSame(
            ['handlers' => ['replaced', 'second']],
            ConfigMerger::merge(
                ['handlers' => ['first', 'second']],
                ['handlers' => [0 => MergeDirective::replace('replaced')]],
            ),
        );
    }

    #[Test]
    public function replaceDirectiveReplacesAnArrayWithoutRecursing(): void
    {
        static::assertSame(
            ['section' => ['replacement' => 'value']],
            ConfigMerger::merge(
                ['section' => ['base' => 1, 'other' => 2]],
                ['section' => MergeDirective::replace(['replacement' => 'value'])],
            ),
        );
    }

    #[Test]
    public function replaceDirectiveReplacesWithNull(): void
    {
        static::assertSame(
            ['section' => null],
            ConfigMerger::merge(['section' => ['base' => 1]], ['section' => MergeDirective::replace(null)]),
        );
    }
}
