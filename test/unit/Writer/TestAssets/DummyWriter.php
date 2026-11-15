<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer\TestAssets;

use Override;
use Webware\ConfigManager\Writer\AbstractWriter;

use function serialize;

class DummyWriter extends AbstractWriter
{
    #[Override]
    protected function processConfig(array $config): string
    {
        return serialize($config);
    }
}
