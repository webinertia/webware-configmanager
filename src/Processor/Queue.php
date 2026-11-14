<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Laminas\Stdlib\PriorityQueue;
use Override;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;

class Queue extends PriorityQueue implements ProcessorInterface
{
    /**
     * Process the whole config structure with each parser in the queue.
     *
     * @return Config
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function process(Config $config)
    {
        if ($config->isReadOnly()) {
            throw new Exception\InvalidArgumentException('Cannot process config because it is read-only');
        }

        foreach ($this as $parser) {
            /** @var ProcessorInterface $parser */
            $parser->process($config);
        }

        return $config;
    }

    /**
     * Process a single value
     *
     * @param  mixed $value
     * @return mixed
     */
    #[Override]
    public function processValue($value)
    {
        foreach ($this as $parser) {
            /** @var ProcessorInterface $parser */
            $value = $parser->processValue($value);
        }

        return $value;
    }
}
