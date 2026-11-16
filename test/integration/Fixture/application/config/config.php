<?php

declare(strict_types=1);

/**
 * An application's config entry point, with this package in place of
 * laminas/laminas-config-aggregator.
 *
 * The cache path comes from the environment so a test can exercise the cold path and the
 * cached path against the same file.
 */

use Webware\ConfigManager\ConfigAggregator;
use Webware\ConfigManager\ConfigProvider;
use Webware\ConfigManager\Provider\PhpFileProvider;

$cachePath = getenv('WEBWARE_SMOKE_CONFIG_CACHE');

$aggregator = new ConfigAggregator(
    [
        ConfigProvider::class,
        new PhpFileProvider(realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php'),
    ],
    false === $cachePath ? null : $cachePath,
);

return $aggregator->getMergedConfig();
