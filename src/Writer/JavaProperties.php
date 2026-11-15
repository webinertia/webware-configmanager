<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Override;
use Webware\ConfigManager\Exception;

use function gettype;
use function is_object;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * @api
 */
final class JavaProperties extends AbstractWriter
{
    public const string DELIMITER_DEFAULT = ':';

    /**
     * Delimiter for key/value pairs.
     *
     * @var string
     */
    private string $delimiter;

    /**
     * @param mixed $delimiter Delimiter to use for key/value pairs; defaults
     *     to self::DELIMITER_DEFAULT (':')
     * @throws Exception\InvalidArgumentException For invalid $delimiter values.
     */
    public function __construct(mixed $delimiter = self::DELIMITER_DEFAULT)
    {
        if (! is_string($delimiter) || '' === $delimiter) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid delimiter of type "%s"; must be a non-empty string',
                is_object($delimiter) ? $delimiter::class : gettype($delimiter),
            ));
        }

        $this->delimiter = $delimiter;
    }

    /**
     * processConfig(): defined by AbstractWriter.
     *
     * @throws Exception\UnprocessableConfigException For non-scalar values in the $config array.
     * @param array<array-key, mixed> $config
     */
    #[Override]
    protected function processConfig(array $config): string
    {
        $string = '';

        foreach ($config as $key => $value) {
            if (! is_scalar($value)) {
                throw new Exception\UnprocessableConfigException(sprintf(
                    '%s configuration writer can only process scalar values; received "%s" for key "%s"',
                    self::class,
                    is_object($value) ? $value::class : gettype($value),
                    $key,
                ));
            }

            $value ??= '';

            $string .= sprintf(
                "%s%s%s\n",
                $key,
                $this->delimiter,
                $value,
            );
        }

        return $string;
    }
}
