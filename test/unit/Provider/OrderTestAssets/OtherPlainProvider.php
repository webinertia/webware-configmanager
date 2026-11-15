<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

/** A second provider that declares no ordering, used to prove relative order survives. */
final class OtherPlainProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
