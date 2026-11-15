<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\MergeTestAssets;

/**
 * Violates the provider contract by returning a non-array.
 */
final class NotAnArrayConfigProvider
{
    public function __invoke(): string
    {
        return 'nope';
    }
}
