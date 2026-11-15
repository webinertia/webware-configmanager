<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Merge;

use function array_key_exists;
use function is_array;
use function is_int;

/**
 * Recursively merges configuration arrays.
 *
 * This replaces Laminas\Stdlib\ArrayUtils::merge() so the package no longer depends on
 * laminas/laminas-stdlib. The rules are the ones laminas-config and the mezzio config
 * aggregator rely on, and they are reproduced exactly:
 *
 * - a key present only in the override is copied across;
 * - an integer key present in both appends the override value;
 * - a string key holding an array on both sides is merged recursively;
 * - any other collision replaces the base value with the override value.
 *
 * A value may be a {@see MergeDirective} instead of data. The directive is honoured
 * with the same precedence as in Laminas\ConfigAggregator\ConfigAggregator::mergeArray,
 * which this reproduces: a replacement applies whether or not the key already exists,
 * and takes effect before the integer-key append rule; a removal only applies to a key
 * that already exists, and is otherwise not stored.
 * @internal
 */
final class ConfigMerger
{
    /**
     * Merge an override onto a base array, recursively.
     *
     * Config values are untyped by design, so every assignment carries mixed.
     *
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $override
     * @return array<array-key, mixed>
     * @mago-expect analysis:mixed-assignment
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $base = $value instanceof MergeDirective
                ? self::applyDirective($base, $key, $value)
                : self::applyValue($base, $key, $value);
        }

        return $base;
    }

    /**
     * Apply a directive to one key.
     *
     * Removal is an unconditional unset, which is a no-op for an absent key, so the
     * directive is never stored as data. Replacement always assigns, which is what
     * makes it take effect for an absent key and beat the integer-key append rule.
     *
     * @param array<array-key, mixed> $base
     * @return array<array-key, mixed>
     */
    private static function applyDirective(array $base, string|int $key, MergeDirective $directive): array
    {
        return match ($directive->kind) {
            MergeDirectiveKind::Remove  => self::withoutKey($base, $key),
            MergeDirectiveKind::Replace => self::withValue($base, $key, $directive->replacement),
        };
    }

    /**
     * @param array<array-key, mixed> $base
     * @return array<array-key, mixed>
     */
    private static function applyValue(array $base, string|int $key, mixed $value): array
    {
        if (! array_key_exists($key, $base)) {
            $base[$key] = $value;
            return $base;
        }

        // Integer keys append rather than recurse. Index 0 of a later file is a
        // new entry, not a replacement for index 0 of an earlier one.
        if (is_int($key)) {
            $base[] = $value;
            return $base;
        }

        $base[$key] = self::combine($base[$key], $value);
        return $base;
    }

    /**
     * @return mixed The incoming value, or both values merged when each is an array.
     */
    private static function combine(mixed $existing, mixed $incoming): mixed
    {
        return is_array($existing) && is_array($incoming)
            ? self::merge($existing, $incoming)
            : $incoming;
    }

    /**
     * @param array<array-key, mixed> $base
     * @return array<array-key, mixed>
     */
    private static function withoutKey(array $base, string|int $key): array
    {
        unset($base[$key]);

        return $base;
    }

    /**
     * @param array<array-key, mixed> $base
     * @return array<array-key, mixed>
     */
    private static function withValue(array $base, string|int $key, mixed $value): array
    {
        $base[$key] = $value;

        return $base;
    }
}
