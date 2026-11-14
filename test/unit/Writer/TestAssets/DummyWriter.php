<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer\TestAssets;

use Webware\ConfigManager\Writer\AbstractWriter;

use function serialize;

class DummyWriter extends AbstractWriter
{
    /**
     * @return string
     */
    public function processConfig(array $config)
    {
        return serialize($config);
    }
}
