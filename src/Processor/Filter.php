<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Laminas\Filter\FilterInterface;
use Override;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;

/**
 * @api
 */
final class Filter implements ProcessorInterface
{
    private FilterInterface $filter;

    /**
     * Filter all config values using the supplied Laminas\Filter
     */
    public function __construct(FilterInterface $filter)
    {
        $this->setFilter($filter);
    }

    /**
     * @return FilterInterface
     */
    public function getFilter(): FilterInterface
    {
        return $this->filter;
    }

    /**
     * Process
     *
     * @return Config
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function process(Config $config): Config
    {
        if ($config->isReadOnly()) {
            throw new Exception\InvalidArgumentException('Cannot process config because it is read-only');
        }

        /**
         * Walk through config and replace values
         */
        foreach ($config as $key => $val) {
            if ($val instanceof Config) {
                $this->process($val);
                continue;
            }

            // @mago-expect analysis:string-member-selector
            $config->$key = $this->filter->filter($val);
        }

        return $config;
    }

    /**
     * Process a single value
     *
     */
    #[Override]
    public function processValue(mixed $value): mixed
    {
        return $this->filter->filter($value);
    }

    public function setFilter(FilterInterface $filter): self
    {
        $this->filter = $filter;
        return $this;
    }
}
