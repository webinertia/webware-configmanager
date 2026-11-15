<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\TestAssets;

/**
 * A provider that cannot satisfy the contract because it requires a constructor
 * argument, which is the situation a class-string reference produces.
 */
final class RequiresArgumentConfigProvider
{
    public function __construct(
        private readonly string $value,
    ) {}

    /** @return array<array-key, mixed> */
    public function __invoke(): array
    {
        return ['value' => $this->value];
    }
}
