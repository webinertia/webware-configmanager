<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Webware\ConfigManager\Config;

/**
 * @api
 */
interface ProcessorInterface
{
    /**
     * Process the whole Config structure and recursively parse all its values.
     *
     * @throws Exception\InvalidArgumentException If the config is read-only.
     */
    public function process(Config $value): Config;

    /**
     * Process a single value
     */
    public function processValue(mixed $value): mixed;
}
