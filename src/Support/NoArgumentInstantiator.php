<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Support;

use ReflectionClass;
use ReflectionException;
use Webware\ConfigManager\Exception;

use function class_exists;
use function sprintf;

/**
 * Instantiates a class that must take no constructor arguments.
 *
 * Both config providers and config processors are named by class-string and built with no
 * arguments, so the checks and the wording live here once rather than in each caller. The
 * label is the caller's noun, so a message reads "Config provider class ..." or
 * "Config processor class ..." rather than a generic phrase that hides what failed.
 *
 * `isInstantiable()` is checked before instantiating because an abstract class raises
 * `Error` rather than `ReflectionException`, and a non-public constructor raises the
 * latter. Checking first turns both, and a constructor that demands arguments, into one
 * reported mistake instead of escaping as an `Error` or `ArgumentCountError`.
 *
 * @internal
 */
final class NoArgumentInstantiator
{
    /**
     * @param string $class The class to build; a missing one is reported, not ignored.
     * @param string $label The noun used in the message, such as "Config provider".
     * @throws Exception\InvalidArgumentException
     */
    public static function instantiate(string $class, string $label): object
    {
        if (! class_exists($class)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s class "%s" does not exist',
                $label,
                $class,
            ));
        }

        try {
            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable()) {
                throw new Exception\InvalidArgumentException(sprintf(
                    '%s class "%s" cannot be instantiated: it is abstract or its constructor '
                        . 'is not public, so supply an instance instead',
                    $label,
                    $class,
                ));
            }

            $constructor = $reflection->getConstructor();

            if (null !== $constructor && $constructor->getNumberOfRequiredParameters() > 0) {
                throw new Exception\InvalidArgumentException(sprintf(
                    '%s class "%s" cannot be instantiated without arguments: its constructor '
                        . 'requires %d',
                    $label,
                    $class,
                    $constructor->getNumberOfRequiredParameters(),
                ));
            }

            return $reflection->newInstance();
        } catch (ReflectionException $exception) {
            // newInstance() declares ReflectionException, and the checks above cover every
            // documented cause, so this keeps the public contract to our own exception
            // rather than letting a reflection failure escape.
            throw new Exception\InvalidArgumentException(sprintf(
                '%s class "%s" cannot be instantiated: %s',
                $label,
                $class,
                $exception->getMessage(),
            ), previous: $exception);
        }
    }
}
