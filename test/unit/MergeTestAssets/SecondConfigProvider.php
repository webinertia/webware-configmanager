<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\MergeTestAssets;

/**
 * Overrides one key of the shared subtree and adds its own.
 */
final class SecondConfigProvider
{
    /** @return array<array-key, mixed> */
    public function __invoke(): array
    {
        return ['shared' => ['b' => 3], 'second' => true];
    }
}
