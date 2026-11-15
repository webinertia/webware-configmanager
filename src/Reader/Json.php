<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

use Override;
use Webware\ConfigManager\Exception;

use function array_replace_recursive;
use function dirname;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function sprintf;
use function trim;

use const JSON_ERROR_NONE;

/**
 * JSON config reader.
 * @api
 */
final class Json implements ReaderInterface
{
    /**
     * Directory of the JSON file
     *
     * @var string
     */
    private ?string $directory = null;

    /**
     * fromFile(): defined by Reader interface.
     *
     * @see    ReaderInterface::fromFile()
     *
     * @throws Exception\RuntimeException
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

        $config = $this->decode(file_get_contents($filename));

        return $this->process($config);
    }

    /**
     * fromString(): defined by Reader interface.
     *
     * @see    ReaderInterface::fromString()
     *
     * @throws Exception\RuntimeException
     * @return array<array-key, mixed>
     */
    #[Override]
    public function fromString(string $string): array
    {
        if ('' === $string) {
            return [];
        }

        $this->directory = null;

        $config = $this->decode($string);

        return $this->process($config);
    }

    /**
     * Decode JSON configuration.
     *
     * Decodes the JSON payload through ext/json and normalises the result into
     * an array, raising an exception when the payload cannot be decoded.
     *
     * @throws Exception\RuntimeException For any decoding errors.
     * @return array<array-key, mixed>
     */
    private function decode(string $data): array
    {
        $config = json_decode($data, associative: true);

        if (null !== $config && ! is_array($config)) {
            throw new Exception\RuntimeException(
                'Invalid JSON configuration; did not return an array or object',
            );
        }

        if (null !== $config) {
            return $config;
        }

        // A "null" payload decodes cleanly, so json_last_error() is NONE, but the
        // result is not an array and cannot be returned from here. It is the same
        // failure as any other non-array document, so it gets the same message
        // rather than a TypeError escaping from the return statement.
        if (JSON_ERROR_NONE === json_last_error()) {
            throw new Exception\RuntimeException(
                'Invalid JSON configuration; did not return an array or object',
            );
        }

        throw new Exception\RuntimeException(json_last_error_msg());
    }

    /**
     * Process the array for @include
     *
     * @throws Exception\RuntimeException
     * @return array<array-key, mixed>
     * @param array<array-key, mixed> $data
     */
    private function process(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->process($value);
            }
            if (is_string($key) && trim($key) === '@include') {
                if (null === $this->directory) {
                    throw new Exception\RuntimeException('Cannot process @include statement for a JSON string');
                }
                $reader = clone $this;
                unset($data[$key]);
                $data = array_replace_recursive($data, $reader->fromFile("{$this->directory}/{$value}"));
            }
        }
        return $data;
    }
}
