<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\OrderTestAssets;

use Webware\ConfigManager\Attribute\LoadAfter;
use Webware\ConfigManager\Attribute\LoadBefore;

/** Uses a variadic argument and two attributes at once. */
#[LoadAfter(EarlyProvider::class, PlainProvider::class)]
#[LoadBefore(LateProvider::class)]
final class MultiTargetProvider
{
    public function __invoke(): array
    {
        return [];
    }
}
