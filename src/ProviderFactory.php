<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use function class_exists;
use function sprintf;

/**
 * Provides an instance of a class named by class-string.
 *
 * Readers, writers, config providers and config processors are all named by class-string and
 * built with no arguments, so the check and the wording live here once rather than in each
 * caller. The name describes the job in the container sense: this provides instances, it is
 * not a factory for config providers specifically. The label is the caller's noun, so a
 * message reads "Config provider class ..." rather than a generic phrase that hides what
 * failed.
 *
 * This is deliberately not a PSR-11 factory and not under `\Container`. It does not resolve a
 * service name against a container and it holds no configuration; it is given a concrete
 * class-string, and either builds it or reports that it does not exist.
 *
 * A class whose constructor requires arguments is a mistake in the caller's configuration, not
 * something to recover from here: nothing in the merge path holds a container, so there is
 * nothing that could satisfy the dependency. Instantiating directly lets PHP describe that
 * precisely. `ArgumentCountError` names the class, the constructor and the argument count,
 * which is more useful than anything this class could rethrow it as. The same applies to an
 * abstract class or a non-public constructor, which raise `Error`. Only absence is checked,
 * because only absence has no standard exception carrying the label the caller supplied.
 *
 * @api
 */
final class ProviderFactory
{
    /**
     * @param string $class The class to build; a missing one is reported, not ignored.
     * @param string $label The noun used in the message, such as "Config provider".
     * @throws Exception\InvalidArgumentException If the class does not exist.
     */
    public static function create(string $class, string $label): object
    {
        if (! class_exists($class)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s class "%s" does not exist',
                $label,
                $class,
            ));
        }

        // class_exists() above narrows the variable to class-string, so the direct
        // instantiation below names a real class. Whether that class is instantiable is
        // not knowable statically: class-string may name the interface itself.
        //
        // This is the same construct laminas/laminas-servicemanager ships in
        // Factory\InvokableFactory, and it is acknowledged there for the same reason
        // (their psalm-baseline.xml carries InvalidStringClass for both of its new
        // expressions). There is no type in the toolchain for "a concrete instantiable
        // class": class-string, class-like-string and interface-string all permit the
        // interface itself, and the enum-string and trait-string forms exclude classes
        // outright. The alternative is reflection, which rewords the ArgumentCountError
        // and Error that PHP already reports precisely.
        //
        // @mago-expect analysis:unknown-class-instantiation
        return new $class();
    }
}
