<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

use Webware\ConfigManager\Attribute\LoadAfter;

#[LoadAfter(CycleAProvider::class)]
final class CycleBProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
