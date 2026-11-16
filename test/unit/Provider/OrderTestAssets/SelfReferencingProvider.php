<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

use Webware\ConfigManager\Attribute\LoadAfter;

/** Names itself, which must be ignored rather than reported as a cycle. */
#[LoadAfter(self::class)]
final class SelfReferencingProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
