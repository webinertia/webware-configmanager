<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Laminas\I18n\Translator\Translator as LaminasTranslator;
use Override;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;

use function is_string;

/**
 * @api
 */
final class Translator implements ProcessorInterface
{
    /**
     * The read side is public, the write side is not, so state cannot be
     * assigned behind the setters' validation.
     *
     * @param non-empty-string $textDomain
     * @param non-empty-string|null $locale
     * @throws Exception\InvalidArgumentException If the text domain or locale is an empty string.
     */
    public function __construct(
        public private(set) LaminasTranslator $translator,
        public private(set) string $textDomain = 'default' {
            set {
                if ('' === $value) {
                    throw new Exception\InvalidArgumentException('textDomain must be a non-empty string');
                }

                $this->textDomain = $value;
            }
        },
        public private(set) ?string $locale = null {
            set {
                if ('' === $value) {
                    throw new Exception\InvalidArgumentException('locale must be a non-empty string or null');
                }

                $this->locale = $value;
            }
        },
    ) {}

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
     * @throws Exception\InvalidArgumentException If the locale is an empty string.
     */
    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @throws Exception\InvalidArgumentException If the text domain is an empty string.
     */
    public function setTextDomain(string $textDomain): self
    {
        $this->textDomain = $textDomain;

        return $this;
    }

    public function setTranslator(LaminasTranslator $translator): self
    {
        $this->translator = $translator;

        return $this;
    }
}
