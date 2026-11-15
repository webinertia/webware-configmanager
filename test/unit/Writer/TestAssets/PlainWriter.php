<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer\TestAssets;

use Override;
use Webware\ConfigManager\Writer\WriterInterface;

/**
 * A writer that implements the interface without extending AbstractWriter, used
 * to prove that a writer resolved from a container is checked before it is used.
 */
final class PlainWriter implements WriterInterface
{
    #[Override]
    public function toFile(string $filename, mixed $config): void
    {
        // Intentionally empty: this writer only exists to be rejected.
    }

    #[Override]
    public function toString(mixed $config): string
    {
        return '';
    }
}
