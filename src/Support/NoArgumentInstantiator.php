<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Support;

use ReflectionClass;
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
     * @mago-expect analysis:unhandled-thrown-type
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

        // Both reflection calls below declare ReflectionException, and neither can raise it
        // here: class_exists() above has already resolved the name, so construction cannot
        // fail, and isInstantiable() has already rejected every class that newInstance()
        // would refuse. That is why the acknowledgement is on the method rather than a
        // catch that could never run.
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
    }
}
