<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

use Webware\ConfigManager\Attribute\LoadBefore;

/** Declares that it must be merged before PlainProvider, as a base package would. */
#[LoadBefore(PlainProvider::class)]
final class EarlyProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
