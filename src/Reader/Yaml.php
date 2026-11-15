<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

use Override;
use Webware\ConfigManager\Exception;

use function call_user_func;
use function file_get_contents;
use function function_exists;
use function is_callable;

/**
 * YAML config reader.
 * @api
 */
final class Yaml extends AbstractReader
{
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
     * Read a file whose existence has already been checked.
     *
     * @throws Exception\RuntimeException If the file cannot be parsed.
     * @return array<array-key, mixed>
     */
    #[Override]
    protected function readFile(string $filename): array
    {
        return $this->withIncludes($this->decode(file_get_contents($filename)));
    }

    /**
     * Read a document that did not come from a file.
     *
     * @throws Exception\RuntimeException If the document cannot be parsed.
     * @return array<array-key, mixed>
     */
    #[Override]
    protected function readString(string $string): array
    {
        return $this->withIncludes($this->decode($string));
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
}
