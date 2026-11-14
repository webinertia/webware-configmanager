<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Laminas\I18n\Translator\Translator as LaminasTranslator;
use Override;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;

class Translator implements ProcessorInterface
{
    /** @var LaminasTranslator */
    protected $translator;

    /** @var string|null */
    protected $locale;

    /** @var string */
    protected $textDomain = 'default';

    /**
     * Translator uses the supplied Laminas\I18n\Translator\Translator to find
     * and translate language strings in config.
     *
     * @param string $textDomain
     * @param string|null $locale
     */
    public function __construct(LaminasTranslator $translator, $textDomain = 'default', $locale = null)
    {
        $this->setTranslator($translator);
        $this->setTextDomain($textDomain);
        $this->setLocale($locale);
    }

    /**
     * @return string|null
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return string
     */
    public function getTextDomain()
    {
        return $this->textDomain;
    }

    /**
     * @return LaminasTranslator
     */
    public function getTranslator()
    {
        return $this->translator;
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

            $config->{$key} = $this->translator->translate($val, $this->textDomain, $this->locale);
        }

        return $config;
    }

    /**
     * Process a single value
     *
     * @param string $value
     * @return string
     */
    #[Override]
    public function processValue($value)
    {
        return $this->translator->translate($value, $this->textDomain, $this->locale);
    }

    /**
     * @param string|null $locale
     * @return self
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * @param string $textDomain
     * @return self
     */
    public function setTextDomain($textDomain)
    {
        $this->textDomain = $textDomain;
        return $this;
    }

    /**
     * @return self
     */
    public function setTranslator(LaminasTranslator $translator)
    {
        $this->translator = $translator;
        return $this;
    }
}
