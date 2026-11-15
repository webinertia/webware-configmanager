<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

/**
 * Provides a configuration array fixed at construction time.
 *
 * This is the drop-in equivalent of Laminas\ConfigAggregator\ArrayProvider. Its purpose
 * is the same one: contributing values computed in the provider list itself, such as the
 * cache settings a config cache needs, without reaching for a file that does not exist
 * or a closure that cannot be cached.
 *
 * @api
 */
final class ArrayProvider
{
    /** @param array<array-key, mixed> $config */
    public function __construct(
        private readonly array $config,
    ) {}

    /** @return array<array-key, mixed> */
    public function __invoke(): array
    {
        return $this->config;
    }
}
