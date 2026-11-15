<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Override;
use SensitiveParameter;
use Traversable;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;

use function array_keys;
use function gettype;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_scalar;
use function is_string;
use function iterator_to_array;
use function strtr;

/**
 * @api
 */
class Token implements ProcessorInterface
{
    /**
     * Token prefix.
     *
     * Assigning either half of the token wrapper invalidates the replacement map.
     */
    public protected(set) string $prefix = '' {
        set {
            $this->map    = null;
            $this->prefix = $value;
        }
    }

    /**
     * Whether or not to process keys as well as values.
     */
    public protected(set) bool $processKeys = false;

    /**
     * Token suffix.
     */
    public protected(set) string $suffix = '' {
        set {
            $this->map    = null;
            $this->suffix = $value;
        }
    }

    /**
     * The registry of tokens
     *
     * @var array<array-key, mixed>
     */
    public protected(set) array $tokens = [];

    /**
     * Replacement map
     *
     * @var array<array-key, mixed>|null
     */
    protected ?array $map = null;

    /**
     * Token Processor walks through a Config structure and replaces all
     * occurrences of tokens with supplied values.
     *
     * @param array|Config|Traversable $tokens Associative array of TOKEN =>
     *     value to replace it with
     * @param bool $enableKeyProcessing Whether or not to enable processing of
     *     token values in configuration keys; defaults to false.
     */
    public function __construct(
        array|Config|Traversable $tokens = [],
        string $prefix = '',
        string $suffix = '',
        bool $enableKeyProcessing = false,
    ) {
        $this->setTokens($tokens);
        $this->setPrefix($prefix);
        $this->setSuffix($suffix);
        if ($enableKeyProcessing) {
            $this->enableKeyProcessing();
        }
    }

    /**
     * Add new token.
     *
     * @throws Exception\InvalidArgumentException
     */
    public function addToken(#[SensitiveParameter] mixed $token, mixed $value): static
    {
        if (! is_scalar($token)) {
            throw new Exception\InvalidArgumentException('Cannot use ' . gettype($token) . ' as token name.');
        }

        // A scalar token name is coerced to an array key by PHP, as before.
        // @mago-expect analysis:invalid-property-assignment-value
        $this->tokens[$token] = $value;

        // reset map
        $this->map = null;

        return $this;
    }

    /**
     * Enable processing keys as well as values.
     *
     */
    public function enableKeyProcessing(): void
    {
        $this->processKeys = true;
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
        return $this->doProcess($config, $this->buildMap());
    }

    /**
     * Process a single value
     *
     */
    #[Override]
    public function processValue(mixed $value): mixed
    {
        return $this->doProcess($value, $this->buildMap());
    }

    public function setPrefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function setSuffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * Add new token.
     *
     */
    public function setToken(#[SensitiveParameter] string $token, mixed $value): static
    {
        return $this->addToken($token, $value);
    }

    /**
     * Set token registry.
     *
     * @param array|Config|Traversable $tokens Associative array of TOKEN =>
     *     value to replace it with
     * @throws Exception\InvalidArgumentException
     */
    public function setTokens(mixed $tokens): static
    {
        $this->tokens = match (true) {
            is_array($tokens)              => $tokens,
            $tokens instanceof Config      => $tokens->toArray(),
            $tokens instanceof Traversable => iterator_to_array($tokens),
            default                        => throw new Exception\InvalidArgumentException(
                'Cannot use ' . gettype($tokens) . ' as token registry.',
            ),
        };

        // reset map
        $this->map = null;

        return $this;
    }

    /**
     * Build replacement map
     *
     * @return array<array-key, mixed>
     */
    protected function buildMap(): array
    {
        if (null !== $this->map) {
            return $this->map;
        }

        $this->map = $this->tokens;

        if ($this->suffix || $this->prefix) {
            $this->map = [];
            foreach ($this->tokens as $token => $value) {
                $this->map[$this->prefix . $token . $this->suffix] = $value;
            }
        }

        foreach (array_keys($this->map) as $key) {
            if ('' !== $key) {
                continue;
            }

            unset($this->map[$key]);
        }

        return $this->map;
    }

    /**
     * Applies replacement map to the given value by modifying the value itself
     *
     * @throws Exception\InvalidArgumentException If the provided value is a read-only {@see Config}.
     * @param array<array-key, mixed> $replacements
     */
    protected function doProcess(mixed $value, array $replacements): mixed
    {
        if ($value instanceof Config) {
            if ($value->isReadOnly()) {
                throw new Exception\InvalidArgumentException('Cannot process config because it is read-only');
            }

            foreach ($value as $key => $val) {
                $newKey         = $this->processKeys ? $this->doProcess($key, $replacements) : $key;
                $value->$newKey = $this->doProcess($val, $replacements);

                // If the processed key differs from the original, remove the original
                if ($newKey !== $key) {
                    // @mago-expect analysis:string-member-selector
                    unset($value->$key);
                }
            }

            return $value;
        }

        if ($value instanceof Traversable || is_array($value)) {
            foreach ($value as &$val) {
                $val = $this->doProcess($val, $replacements);
            }

            return $value;
        }

        if (! is_string($value) && (is_bool($value) || is_numeric($value))) {
            $stringVal  = (string) $value;
            $changedVal = strtr($stringVal, $replacements);

            // replace the value only if a string replacement occurred
            if ($changedVal !== $stringVal) {
                return $changedVal;
            }

            return $value;
        }

        return strtr((string) $value, $replacements);
    }
}
