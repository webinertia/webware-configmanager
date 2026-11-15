<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

use Override;
use Webware\ConfigManager\Exception;

use function array_replace_recursive;
use function call_user_func;
use function dirname;
use function file_get_contents;
use function function_exists;
use function is_array;
use function is_callable;
use function is_file;
use function is_readable;
use function is_string;
use function sprintf;
use function trim;

/**
 * YAML config reader.
 * @api
 */
final class Yaml implements ReaderInterface
{
    /**
     * Directory of the YAML file
     */
    private ?string $directory = null;

    /**
     * YAML decoder callback
     *
     * @var callable|null
     */
    private mixed $yamlDecoder = null;

    /**
     * Constructor
     */
    public function __construct(mixed $yamlDecoder = null)
    {
        if (null !== $yamlDecoder) {
            $this->setYamlDecoder($yamlDecoder);
            return;
        }

        if (function_exists('yaml_parse')) {
            $this->setYamlDecoder('yaml_parse');
        }
    }

    /**
     * fromFile(): defined by Reader interface.
     *
     * @see    ReaderInterface::fromFile()
     * @throws Exception\RuntimeException If the file cannot be read or parsed.
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fromFile(string $filename): array
    {
        if (! is_file($filename) || ! is_readable($filename)) {
            throw new Exception\RuntimeException(sprintf(
                "File '%s' doesn't exist or not readable",
                $filename,
            ));
        }

        $this->directory = dirname($filename);

        return $this->process($this->decode(file_get_contents($filename)));
    }

    /**
     * fromString(): defined by Reader interface.
     *
     * @see    ReaderInterface::fromString()
     * @throws Exception\RuntimeException If the string cannot be parsed.
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fromString(string $string): array
    {
        if ('' === $string) {
            return [];
        }

        $this->directory = null;

        return $this->process($this->decode($string));
    }

    /**
     * Get callback for decoding YAML
     */
    public function getYamlDecoder(): ?callable
    {
        return $this->yamlDecoder;
    }

    /**
     * Set callback for decoding YAML
     *
     * @throws Exception\RuntimeException If the decoder is not callable.
     */
    public function setYamlDecoder(mixed $yamlDecoder): self
    {
        if (! is_callable($yamlDecoder)) {
            throw new Exception\RuntimeException(
                'Invalid parameter to setYamlDecoder() - must be callable',
            );
        }

        $this->yamlDecoder = $yamlDecoder;
        return $this;
    }

    /**
     * Decode a YAML document into an array.
     *
     * @throws Exception\RuntimeException If no decoder is configured or decoding fails.
     * @return array<array-key, mixed>
     */
    private function decode(string|false $input): array
    {
        $decoder = $this->yamlDecoder ?? throw new Exception\RuntimeException(
            "You didn't specify a Yaml callback decoder",
        );

        $config = call_user_func($decoder, $input);

        if (null === $config) {
            throw new Exception\RuntimeException('Error parsing YAML data');
        }

        // @mago-expect analysis:mixed-return-statement
        return $config;
    }

    /**
     * Process the array for @include
     *
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     * @throws Exception\RuntimeException
     */
    private function process(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->process($value);
            }

            if (is_string($key) && trim($key) === '@include') {
                if (null === $this->directory) {
                    throw new Exception\RuntimeException('Cannot process @include statement for a json string');
                }

                $reader = clone $this;
                unset($data[$key]);
                $data = array_replace_recursive($data, $reader->fromFile("{$this->directory}/{$value}"));
            }
        }

        return $data;
    }
}
