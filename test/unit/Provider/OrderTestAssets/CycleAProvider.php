<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

use Webware\ConfigManager\Attribute\LoadAfter;

#[LoadAfter(CycleBProvider::class)]
final class CycleAProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
