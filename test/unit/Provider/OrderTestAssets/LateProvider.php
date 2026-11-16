<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

use Webware\ConfigManager\Attribute\LoadAfter;

/** Declares that it must be merged after PlainProvider, as an overriding package would. */
#[LoadAfter(PlainProvider::class)]
final class LateProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
