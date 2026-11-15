<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\MergeTestAssets;

/**
 * Stands in for a component ConfigProvider: no constructor, returns an array.
 */
final class FirstConfigProvider
{
    /** @return array<array-key, mixed> */
    public function __invoke(): array
    {
        return ['shared' => ['a' => 1, 'b' => 2], 'first' => true];
    }
}
