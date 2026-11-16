<?php

declare(strict_types=1);

/**
 * Builds the application container the way every Mezzio application does: the merged
 * config becomes the `config` service, and the service manager is constructed from the
 * `dependencies` section of it.
 *
 * Nothing here is Mezzio-specific. If this container resolves what the merged config
 * declares, a Mezzio application consuming it resolves the same things.
 */

use Laminas\ServiceManager\ServiceManager;

$config                             = require __DIR__ . '/config.php';
$dependencies                       = $config['dependencies'];
$dependencies['services']['config'] = $config;

return new ServiceManager($dependencies);
