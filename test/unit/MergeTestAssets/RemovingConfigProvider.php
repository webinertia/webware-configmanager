<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\MergeTestAssets;

use Webware\ConfigManager\Merge\MergeDirective;

/**
 * Steers the merge rather than merely contributing values.
 */
final class RemovingConfigProvider
{
    /** @return array<array-key, mixed> */
    public function __invoke(): array
    {
        return ['shared' => MergeDirective::remove()];
    }
}
