<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Processor\TestAssets;

use Webware\ConfigManager\Provider\ArrayProvider;

/** Injects a provider, proving a class-string processor is instantiated and applied. */
final class AddsInjectedProviderPreProcessor
{
    /**
     * @param iterable<class-string|callable> $providers
     * @return list<class-string|callable>
     */
    public function __invoke(iterable $providers): array
    {
        $list = [];

        foreach ($providers as $provider) {
            $list[] = $provider;
        }

        $list[] = new ArrayProvider(['injected' => true]);

        return $list;
    }
}
