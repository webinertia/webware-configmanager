<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\TestAssets;

/**
 * Returns a fixed configuration array, standing in for a component ConfigProvider.
 */
final class ArrayConfigProvider
{
    /** @param array<array-key, mixed> $config */
    public function __construct(
        private array $config = [],
    ) {}

    /** @return array<array-key, mixed> */
    public function __invoke(): array
    {
        return $this->config;
    }
}
