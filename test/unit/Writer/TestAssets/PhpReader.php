<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer\TestAssets;

use function is_array;

class PhpReader
{
    /**
     * @return array<array-key, mixed>
     */
    public function fromFile(string $filename): array
    {
        $config = include $filename;

        return is_array($config) ? $config : [];
    }
}
