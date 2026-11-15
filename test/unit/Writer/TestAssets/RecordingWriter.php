<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer\TestAssets;

use Override;
use Webware\ConfigManager\Writer\AbstractWriter;

use function serialize;

/**
 * Records the file name that serialization was given, so a test can prove the
 * destination was resolved before the content was built.
 */
final class RecordingWriter extends AbstractWriter
{
    public static ?string $lastFilename = null;

    #[Override]
    protected function processConfig(array $config): string
    {
        return serialize($config);
    }

    #[Override]
    protected function toContent(mixed $config, string $filename): string
    {
        self::$lastFilename = $filename;

        return parent::toContent($config, $filename);
    }
}
