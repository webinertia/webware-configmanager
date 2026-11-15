<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function is_array;

/**
 * Reads the container's `config` entry as an array.
 *
 * One place knows how the `config` service is treated, so the resolution rules cannot
 * drift between callers: absent, non-array and container errors all collapse to "no
 * entries", and a caller only has to answer whether its key is there.
 *
 * @internal
 */
final class ConfigService
{
    /**
     * @return array<array-key, mixed>
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function entriesOf(ContainerInterface $container): array
    {
        $config = $container->has('config') ? $container->get('config') : [];

        return is_array($config) ? $config : [];
    }
}
