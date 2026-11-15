<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Webware\ConfigManager\Exception;
use Webware\FileWriter\Exception as WriterException;

/**
 * @api
 */
interface WriterInterface
{
    /**
     * Write a config object to a file.
     *
     * The file is replaced atomically, so a reader sees either the previous
     * document or the new one and never a partial write.
     *
     * @throws WriterException\InvalidArgumentException If the file name is empty.
     * @throws WriterException\RuntimeException If the link cannot be resolved or the
     *     file cannot be written.
     * @throws Exception\InvalidArgumentException If the config cannot be serialized.
     */
    public function toFile(string $filename, mixed $config): void;

    /**
     * Write a config object to a string.
     *
     * @throws Exception\InvalidArgumentException If the config cannot be serialized.
     */
    public function toString(mixed $config): string;
}
