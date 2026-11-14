<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Laminas\Filter\FilterInterface as LaminasFilter;
use Override;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;

class Filter implements ProcessorInterface
{
    /** @var LaminasFilter */
    protected $filter;

    /**
     * Filter all config values using the supplied Laminas\Filter
     */
    public function __construct(LaminasFilter $filter)
    {
        $this->setFilter($filter);
    }

    /**
     * @return LaminasFilter
     */
    public function getFilter()
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
    public function process(Config $config)
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

            $config->$key = $this->filter->filter($val);
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
        return $this->filter->filter($value);
    }

    /**
     * @return self
     */
    public function setFilter(LaminasFilter $filter)
    {
        $this->filter = $filter;
        return $this;
    }
}
