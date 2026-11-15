<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

/**
 * @api
 */
interface ReaderInterface
{
    /**
     * Read from a file and create an array
     *
     * @return array<array-key, mixed>
     */
    public function fromFile(string $filename): array;

    /**
     * Read from a string and create an array
     *
     * @return array<array-key, mixed>
     */
    public function fromString(string $string): array;
}
