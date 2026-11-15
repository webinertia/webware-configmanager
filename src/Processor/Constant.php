<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Override;

use function class_exists;
use function constant;
use function defined;
use function get_defined_constants;
use function is_string;
use function preg_match;
use function str_contains;
use function substr;

/**
 * @api
 */
final class Constant extends Token implements ProcessorInterface
{
    /**
     * Replace only user-defined tokens
     *
     * @var bool
     */
    private bool $userOnly = true;

    /**
     * Constant Processor walks through a Config structure and replaces all
     * PHP constants with their respective values
     *
     * @param bool $userOnly True to process only user-defined constants,
     *     false to process all PHP constants; defaults to true.
     * @param string $prefix Optional prefix
     * @param string $suffix Optional suffix
     * @param bool $enableKeyProcessing Whether or not to enable processing of
     *     constant values in configuration keys; defaults to false.
     */
    public function __construct(
        bool $userOnly = true,
        string $prefix = '',
        string $suffix = '',
        bool $enableKeyProcessing = false,
    ) {
        $this->setUserOnly($userOnly);
        $this->setPrefix($prefix);
        $this->setSuffix($suffix);

        if ($enableKeyProcessing) {
            $this->enableKeyProcessing();
        }

        $this->loadConstants();
    }

    public function getUserOnly(): bool
    {
        return $this->userOnly;
    }

    /**
     * Load all currently defined constants into parser.
     *
     */
    public function loadConstants(): void
    {
        if ($this->userOnly) {
            $constants = get_defined_constants(true);
            $this->setTokens($constants['user'] ?? []);
            return;
        }

        $this->setTokens(get_defined_constants());
    }

    /**
     * Should we use only user-defined constants?
     *
     */
    public function setUserOnly(bool $userOnly): self
    {
        $this->userOnly = $userOnly;
        return $this;
    }

    /**
     * Override processing of individual value.
     *
     * If the value is a string and evaluates to a class constant, returns
     * the class constant value; otherwise, delegates to the parent.
     *
     * @param array<array-key, mixed> $replacements
     */
    #[Override]
    protected function doProcess(mixed $value, array $replacements): mixed
    {
        if (! is_string($value)) {
            return parent::doProcess($value, $replacements);
        }

        if (! str_contains($value, '::')) {
            return parent::doProcess($value, $replacements);
        }

        // Handle class constants
        if (defined($value)) {
            return constant($value);
        }

        // Handle ::class notation
        if (! preg_match('/::class$/i', $value)) {
            return parent::doProcess($value, $replacements);
        }

        $class = substr(
            string: $value,
            offset: 0,
            length: -7,
        );
        if (class_exists($class)) {
            return $class;
        }

        // While we've matched ::class, the class does not exist, and PHP will
        // raise an error if you try to define a constant using that notation.
        // As such, we have something that cannot possibly be a constant, so we
        // can safely return the value verbatim.
        return $value;
    }
}
