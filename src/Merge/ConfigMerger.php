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
 * The marker objects ArrayUtils understood (MergeReplaceKey and MergeRemoveKey) are not
 * supported, because they are classes of the package this replaces. A consumer that
 * needs a key removed can unset it before merging.
 */
final class ConfigMerger
{
    /**
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $override
     * @return array<array-key, mixed>
     */
    // Config values are untyped by design, so every assignment carries mixed.
    // @mago-expect analysis:mixed-assignment
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (! array_key_exists($key, $base)) {
                $base[$key] = $value;
                continue;
            }

            // Integer keys append rather than recurse. Index 0 of a later file is a
            // new entry, not a replacement for index 0 of an earlier one.
            if (is_int($key)) {
                $base[] = $value;
                continue;
            }

            $base[$key] = self::combine($base[$key], $value);
        }

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
}
