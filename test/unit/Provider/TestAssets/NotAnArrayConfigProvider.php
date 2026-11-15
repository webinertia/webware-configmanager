<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\TestAssets;

/**
 * A provider that violates the contract by returning something other than an array.
 */
final class NotAnArrayConfigProvider
{
    public function __invoke(): string
    {
        return 'not-an-array';
    }
}
