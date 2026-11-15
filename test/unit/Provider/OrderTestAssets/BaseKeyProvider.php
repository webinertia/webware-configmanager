<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

/** Contributes the base value of a shared key. */
final class BaseKeyProvider
{
    /** @return array<array-key, mixed> */
    public function __invoke(): array
    {
        return ['shared' => 'base'];
    }
}
