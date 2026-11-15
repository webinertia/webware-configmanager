<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

use Webware\ConfigManager\Attribute\LoadBefore;

/** Names itself with LoadBefore, which must be ignored rather than become a cycle. */
#[LoadBefore(self::class)]
final class SelfBeforeProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
