<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

class InvalidArgumentException extends BaseInvalidArgumentException implements ExceptionInterface {}
