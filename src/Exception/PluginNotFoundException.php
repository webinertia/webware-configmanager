<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Exception;

use Psr\Container\NotFoundExceptionInterface;

class PluginNotFoundException extends RuntimeException implements NotFoundExceptionInterface {}
