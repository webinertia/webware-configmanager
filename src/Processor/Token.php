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

class Token implements ProcessorInterface
{
    /**
     * Token prefix.
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Whether or not to process keys as well as values.
     *
     * @var bool
     */
    protected $processKeys = false;

    /**
     * Token suffix.
     *
     * @var string
     */
    protected $suffix = '';

    /**
     * The registry of tokens
     *
     * @var array
     */
    protected $tokens = [];

    /**
     * Replacement map
     *
     * @var array
     */
    protected $map;

    /**
     * Token Processor walks through a Config structure and replaces all
     * occurrences of tokens with supplied values.
     *
     * @param array|Config|Traversable $tokens Associative array of TOKEN =>
     *     value to replace it with
     * @param string $prefix
     * @param string $suffix
     * @param bool $enableKeyProcessing Whether or not to enable processing of
     *     token values in configuration keys; defaults to false.
     */
    public function __construct($tokens = [], $prefix = '', $suffix = '', $enableKeyProcessing = false)
    {
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
     * @param string $token
     * @param mixed $value
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function addToken(#[SensitiveParameter] $token, $value)
    {
        if (! is_scalar($token)) {
            throw new Exception\InvalidArgumentException('Cannot use ' . gettype($token) . ' as token name.');
        }
        $this->tokens[$token] = $value;

        // reset map
        $this->map = null;

        return $this;
    }

    /**
     * Enable processing keys as well as values.
     *
     * @return void
     */
    public function enableKeyProcessing()
    {
        $this->processKeys = true;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @return string
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

    /**
     * Get current token registry.
     *
     * @return array
     */
    public function getTokens()
    {
        return $this->tokens;
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
        return $this->doProcess($config, $this->buildMap());
    }

    /**
     * Process a single value
     *
     * @param mixed $value
     * @return mixed
     */
    #[Override]
    public function processValue($value)
    {
        return $this->doProcess($value, $this->buildMap());
    }

    /**
     * @param  string $prefix
     * @return self
     */
    public function setPrefix($prefix)
    {
        // reset map
        $this->map    = null;
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * @param  string $suffix
     * @return self
     */
    public function setSuffix($suffix)
    {
        // reset map
        $this->map    = null;
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * Add new token.
     *
     * @param string $token
     * @param mixed $value
     * @return self
     */
    public function setToken(#[SensitiveParameter] $token, $value)
    {
        return $this->addToken($token, $value);
    }

    /**
     * Set token registry.
     *
     * @param array|Config|Traversable $tokens Associative array of TOKEN =>
     *     value to replace it with
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setTokens($tokens)
    {
        $this->tokens = match (true) {
            is_array($tokens) => $tokens,
            $tokens instanceof Config => $tokens->toArray(),
            $tokens instanceof Traversable => iterator_to_array($tokens),
            default => throw new Exception\InvalidArgumentException(
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
     * @return array
     */
    protected function buildMap()
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
     * @param mixed $value
     * @return mixed
     * @throws Exception\InvalidArgumentException If the provided value is a read-only {@see Config}.
     */
    protected function doProcess($value, array $replacements)
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
            $changedVal = strtr($stringVal, $this->map);

            // replace the value only if a string replacement occurred
            if ($changedVal !== $stringVal) {
                return $changedVal;
            }

            return $value;
        }

        return strtr((string) $value, $this->map);
    }
}
