<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer\TestAssets;

use Webware\ConfigManager\Writer\WriterInterface;

/**
 * A writer that implements the interface without extending AbstractWriter, used
 * to prove that a writer resolved from a container is checked before it is used.
 */
final class PlainWriter implements WriterInterface
{
    public function toFile($filename, $config, $exclusiveLock = true)
    {
        return null;
    }

    public function toString($config)
    {
        return '';
    }
}
