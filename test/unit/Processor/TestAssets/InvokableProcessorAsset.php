<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Processor\TestAssets;

/** An invokable processor with no constructor arguments, usable as a class-string. */
final class InvokableProcessorAsset
{
    /** @param array<array-key, mixed> $config @return array<array-key, mixed> */
    public function __invoke(array $config): array
    {
        return $config;
    }
}
