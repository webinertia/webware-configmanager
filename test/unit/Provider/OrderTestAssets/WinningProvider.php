<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

use Webware\ConfigManager\Attribute\LoadAfter;

/** Must win the shared key even when the installer lists it first. */
#[LoadAfter(BaseKeyProvider::class)]
final class WinningProvider
{
    /** @return array<array-key, mixed> */
    public function __invoke(): array
    {
        return ['shared' => 'winner'];
    }
}
