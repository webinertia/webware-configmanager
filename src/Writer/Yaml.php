<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Override;
use Webware\ConfigManager\Exception;

use function call_user_func;
use function function_exists;
use function is_callable;

/**
 * @api
 */
final class Yaml extends AbstractWriter
{
    /**
     * YAML encoder callback
     *
     * @var callable|null
     */
    private mixed $yamlEncoder = null;

    /**
     * Constructor
     */
    public function __construct(mixed $yamlEncoder = null)
    {
        if (null !== $yamlEncoder) {
            $this->setYamlEncoder($yamlEncoder);
            return;
        }

        if (function_exists('yaml_emit')) {
            $this->setYamlEncoder('yaml_emit');
        }
    }

    /**
     * Get callback for encoding YAML
     */
    public function getYamlEncoder(): ?callable
    {
        return $this->yamlEncoder;
    }

    /**
     * Set callback for encoding YAML
     *
     * @throws Exception\InvalidArgumentException If the encoder is not callable.
     */
    public function setYamlEncoder(mixed $yamlEncoder): self
    {
        if (! is_callable($yamlEncoder)) {
            throw new Exception\InvalidArgumentException(
                'Invalid parameter to setYamlEncoder() - must be callable',
            );
        }

        $this->yamlEncoder = $yamlEncoder;
        return $this;
    }

    /**
     * processConfig(): defined by AbstractWriter.
     *
     * @throws Exception\RuntimeException If no encoder is configured or generation fails.
     * @param array<array-key, mixed> $config
     */
    #[Override]
    protected function processConfig(array $config): string
    {
        $encoder = $this->yamlEncoder ?? throw new Exception\RuntimeException(
            "You didn't specify a Yaml callback encoder",
        );

        $encoded = call_user_func($encoder, $config);
        if (null === $encoded) {
            throw new Exception\RuntimeException('Error generating YAML data');
        }

        return $encoded;
    }
}
