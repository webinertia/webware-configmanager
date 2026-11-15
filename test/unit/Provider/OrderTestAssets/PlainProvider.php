<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

/** A provider that declares no ordering, so it keeps whatever position it is given. */
final class PlainProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
