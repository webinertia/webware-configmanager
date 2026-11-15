<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

use Not\A\Real\Provider;
use Webware\ConfigManager\Attribute\LoadAfter;

/** Names a provider that is deliberately not installed. */
#[LoadAfter(Provider::class)]
final class DanglingProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
