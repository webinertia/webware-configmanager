<?php

declare(strict_types=1);

/**
 * Application configuration, loaded through this package's PhpFileProvider.
 */

use Webware\ConfigManager\Cache\ConfigCache;

return [
    // The cache is only written when the merged configuration says so, and only read when
    // config\config.php was given a path. The test supplies the path.
    ConfigCache::ENABLE_CACHE => true,
    'application'             => [
        'greeting' => 'hello from the fixture',
    ],
];
