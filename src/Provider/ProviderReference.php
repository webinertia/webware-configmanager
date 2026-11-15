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
    private function __construct(
        public readonly mixed $value,
        public readonly string $identity,
        public readonly string $label,
    ) {}

    public static function of(mixed $value): self
    {
        return new self($value, self::identityOf($value), self::labelOf($value));
    }

    /**
     * Distinguish two references to the same provider.
     *
     * A class-string is its own identity. An instance is identified by object handle,
     * so two instances of one class are not mistaken for a duplicate.
     */
    private static function identityOf(mixed $value): string
    {
        if (is_string($value)) {
            return "class:{$value}";
        }

        if (is_object($value)) {
            $id    = spl_object_id($value);
            $class = $value::class;

            return "object:{$class}:{$id}";
        }

        $type = get_debug_type($value);

        return "other:{$type}";
    }

    /**
     * Name a reference for an error message.
     */
    private static function labelOf(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Closure) {
            return 'Closure';
        }

        $type = get_debug_type($value);

        return is_object($value) ? $value::class : $type;
    }
}
