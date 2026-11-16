<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

use Closure;

use function get_debug_type;
use function is_object;
use function is_string;
use function spl_object_id;

/**
 * A single entry from a provider list, with the parts used to report on it.
 *
 * Keeping the identity and the human label together means a reference is classified
 * once, and an error message and a duplicate check can never disagree about what the
 * caller actually supplied.
 *
 * @internal
 */
final class ProviderReference
{
    /**
     * @param null|string $class The class the reference denotes, or null when it denotes none.
     */
    private function __construct(
        public readonly mixed $value,
        public readonly string $identity,
        public readonly string $label,
        public readonly ?string $class,
    ) {}

    public static function of(mixed $value): self
    {
        $class = self::classOf($value);

        return new self($value, self::identityOf($value, $class), self::labelOf($value), $class);
    }

    /**
     * The class a reference denotes.
     *
     * A declaration names a class, so this is the single place that decides what an
     * entry in a provider list is called. Both a class-string and an instance name
     * their class; anything else names none, which is also what an ordering
     * declaration can never match.
     */
    private static function classOf(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => $value,
            is_object($value) => $value::class,
            default           => null,
        };
    }

    /**
     * Distinguish two references to the same provider.
     *
     * A class-string is its own identity. An instance is identified by object handle,
     * so two instances of one class are not mistaken for a duplicate.
     */
    private static function identityOf(mixed $value, ?string $class): string
    {
        if (null === $class) {
            $type = get_debug_type($value);

            return "other:{$type}";
        }

        if (! is_object($value)) {
            return "class:{$class}";
        }

        $id = spl_object_id($value);

        return "object:{$class}:{$id}";
    }

    /**
     * Name a reference for an error message.
     *
     * A class-string is named as itself; everything else is named by its type, which
     * for an object is already its class name and for a Closure is already "Closure".
     */
    private static function labelOf(mixed $value): string
    {
        return is_string($value) ? $value : get_debug_type($value);
    }
}
