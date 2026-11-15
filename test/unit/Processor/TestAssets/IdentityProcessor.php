<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Processor\TestAssets;

use Override;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Processor\ProcessorInterface;

/**
 * A processor that changes nothing, so queue ordering can be asserted on the processors
 * themselves rather than on the effect they have.
 */
final class IdentityProcessor implements ProcessorInterface
{
    #[Override]
    public function process(Config $config): Config
    {
        return $config;
    }

    #[Override]
    public function processValue(mixed $value): mixed
    {
        return $value;
    }
}
