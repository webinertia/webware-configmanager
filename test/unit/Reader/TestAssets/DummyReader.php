<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Reader\TestAssets;

use Override;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader\ReaderInterface;

use function file_get_contents;
use function is_array;
use function is_readable;
use function unserialize;

class DummyReader implements ReaderInterface
{
    #[Override]
    public function fromFile(string $filename): array
    {
        if (! is_readable($filename)) {
            throw new Exception\RuntimeException("File '{$filename}' doesn't exist or not readable");
        }

        return $this->decode(file_get_contents($filename));
    }

    #[Override]
    public function fromString(string $string): array
    {
        if ('' === $string) {
            return [];
        }

        return $this->decode($string);
    }

    private function decode(string $payload): array
    {
        $decoded = unserialize($payload);

        if (! is_array($decoded)) {
            throw new Exception\RuntimeException('The serialized payload is not an array');
        }

        return $decoded;
    }
}
