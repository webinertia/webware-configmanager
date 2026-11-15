<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Override;
use Webware\ConfigManager\Exception;

use function json_encode;
use function json_last_error_msg;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * @api
 */
final class Json extends AbstractWriter
{
    /**
     * processConfig(): defined by AbstractWriter.
     *
     * @throws Exception\RuntimeException If encoding errors occur.
     * @param array<array-key, mixed> $config
     */
    #[Override]
    protected function processConfig(array $config): string
    {
        $serialized = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if (false === $serialized) {
            throw new Exception\RuntimeException(json_last_error_msg());
        }

        return $serialized;
    }
}
