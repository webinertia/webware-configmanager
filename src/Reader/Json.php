<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

use Override;
use Webware\ConfigManager\Exception;

use function file_get_contents;
use function is_array;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;

use const JSON_ERROR_NONE;

/**
 * JSON config reader.
 * @api
 */
final class Json extends AbstractReader
{
    #[Override]
    protected function includeStatementMessage(): string
    {
        return 'Cannot process @include statement for a JSON string';
    }

    /**
     * @throws Exception\RuntimeException
     * @return array<array-key, mixed>
     */
    #[Override]
    protected function readFile(string $filename): array
    {
        return $this->withIncludes($this->decode(file_get_contents($filename)));
    }

    /**
     * @throws Exception\RuntimeException
     * @return array<array-key, mixed>
     */
    #[Override]
    protected function readString(string $string): array
    {
        return $this->withIncludes($this->decode($string));
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
}
