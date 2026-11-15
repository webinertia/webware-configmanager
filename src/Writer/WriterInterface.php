<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

interface WriterInterface
{
    /**
     * Write a config object to a file.
     *
     * The file is replaced atomically, so a reader sees either the previous
     * document or the new one and never a partial write.
     *
     * @param  string  $filename
     * @param  mixed   $config
     * @param  bool $exclusiveLock Ignored; retained for interface compatibility.
     * @return void
     */
    public function toFile($filename, $config, $exclusiveLock = true);

    /**
     * Write a config object to a string.
     *
     * @param  mixed $config
     * @return string
     */
    public function toString($config);
}
