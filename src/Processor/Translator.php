<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Laminas\I18n\Translator\Translator as LaminasTranslator;
use Override;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;

use function is_string;

class Translator implements ProcessorInterface
{
    /** @var LaminasTranslator */
    protected $translator;

    /** @var non-empty-string|null */
    protected ?string $locale = null;

    /** @var non-empty-string */
    protected string $textDomain = 'default';

    /**
     * Translator uses the supplied Laminas\I18n\Translator\Translator to find
     * and translate language strings in config.
     *
     * @throws Exception\InvalidArgumentException If the text domain or locale is an empty string.
     */
    public function __construct(LaminasTranslator $translator, string $textDomain = 'default', ?string $locale = null)
    {
        $this->setTranslator($translator);
        $this->setTextDomain($textDomain);
        $this->setLocale($locale);
    }

    /**
     * @return non-empty-string|null
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @return non-empty-string
     */
    public function getTextDomain(): string
    {
        return $this->textDomain;
    }

    public function getTranslator(): LaminasTranslator
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

            $config->{$key} = $this->processValue($val);
        }

        return $config;
    }

    /**
     * Process a single value
     */
    #[Override]
    public function processValue(mixed $value): mixed
    {
        if (! is_string($value) || '' === $value) {
            return $value;
        }

        return $this->translator->translate($value, $this->textDomain, $this->locale);
    }

    /**
     * @return self
     * @throws Exception\InvalidArgumentException If the locale is an empty string.
     */
    public function setLocale(?string $locale): self
    {
        if ('' === $locale) {
            throw new Exception\InvalidArgumentException('locale must be a non-empty string or null');
        }

        $this->locale = $locale;
        return $this;
    }

    /**
     * @return self
     * @throws Exception\InvalidArgumentException If the text domain is an empty string.
     */
    public function setTextDomain(string $textDomain): self
    {
        if ('' === $textDomain) {
            throw new Exception\InvalidArgumentException('textDomain must be a non-empty string');
        }

        $this->textDomain = $textDomain;
        return $this;
    }

    /**
     * @return self
     */
    public function setTranslator(LaminasTranslator $translator): self
    {
        $this->translator = $translator;
        return $this;
    }
}
