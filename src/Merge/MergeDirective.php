<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Merge;

/**
 * A directive a config provider can place instead of a value to steer the merge.
 *
 * This provides the parity replacement for Laminas\Stdlib\ArrayUtils\MergeRemoveKey
 * and MergeReplaceKey. A replacement has to carry the value it substitutes, and an
 * enum case cannot hold a property, so this is a value object discriminated by
 * {@see MergeDirectiveKind}.
 *
 * @api
 */
final class MergeDirective
{
    private function __construct(
        public private(set) MergeDirectiveKind $kind,
        public private(set) mixed $replacement = null,
    ) {}

    /**
     * Drop the key from the merged result.
     */
    public static function remove(): self
    {
        return new self(MergeDirectiveKind::Remove);
    }

    /**
     * Replace the whole value, without recursing into it.
     */
    public static function replace(mixed $value): self
    {
        return new self(MergeDirectiveKind::Replace, $value);
    }
}
