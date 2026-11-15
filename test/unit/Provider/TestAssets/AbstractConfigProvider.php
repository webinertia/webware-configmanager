<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider\TestAssets;

/**
 * Abstract, so it can be referenced as a class-string but never instantiated.
 * That is the only way to reach the instantiation failure branch.
 */
abstract class AbstractConfigProvider
{
    /** @return array<array-key, mixed> */
    abstract public function __invoke(): array;
}
