<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

use Webware\ConfigManager\Attribute\LoadAfter;

/** Exposes a fixed config so an end-to-end merge can be asserted. */
#[LoadAfter(PlainProvider::class)]
final class ConfigProvider
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
