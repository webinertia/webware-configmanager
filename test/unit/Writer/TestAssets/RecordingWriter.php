<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer\TestAssets;

use Webware\ConfigManager\Writer\AbstractWriter;

use function serialize;

/**
 * Records the file name that serialization was given, so a test can prove the
 * destination was resolved before the content was built.
 */
final class RecordingWriter extends AbstractWriter
{
    public static ?string $lastFilename = null;

    public function processConfig(array $config)
    {
        return serialize($config);
    }

    protected function toContent(mixed $config, string $filename): string
    {
        self::$lastFilename = $filename;

        return parent::toContent($config, $filename);
    }
}
