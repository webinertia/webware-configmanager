<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use Psl\Type;

use function get_debug_type;
use function sprintf;

/**
 * Asserts that a value produced by a callable or a file has the shape its contract needs.
 *
 * The same check was written at five boundaries: a config provider's return, a
 * post-processor's, a pre-processor's, a config file's, and a cache file's. Each one was
 * the same test with its own hand-written message, and the exception class disagreed
 * between them. It is stated once here.
 *
 * The shape is named by a PSL type rather than by a language construct, so tightening one
 * is a one-word change and the intent is written down. `matches()` is deliberate rather
 * than `coerce()`: coercion accepts a Traversable, and every one of these boundaries
 * requires an array, so coercion would silently widen the contract. `ProviderReference`
 * labels, `get_debug_type()` and the subject together mean a failure names the provider or
 * the file it came from, which is the only reason to check at all: the declared return
 * types already make the wrong type impossible to return, so what is being bought here is
 * a message and a stable exception rather than safety.
 *
 * @internal
 */
final class Shape
{
    /**
     * Assert a configuration array.
     *
     * @param string $subject What produced the value, for the message.
     * @return array<array-key, mixed>
     * @throws Exception\InvalidArgumentException
     */
    public static function array(mixed $value, string $subject): array
    {
        if (! Type\mixed_dict()->matches($value)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s must return an array; it returned "%s"',
                $subject,
                get_debug_type($value),
            ));
        }

        return $value;
    }

    /**
     * Assert an iterable of provider references.
     *
     * The element types are `mixed()` deliberately. With anything narrower, `matches()`
     * refuses a Generator, because it cannot iterate one to check it, and a pre-processor
     * returning a Generator is valid.
     *
     * @param string $subject What produced the value, for the message.
     * @return iterable<class-string|callable>
     * @throws Exception\InvalidArgumentException
     */
    public static function providerList(mixed $value, string $subject): iterable
    {
        if (! Type\iterable(Type\mixed(), Type\mixed())->matches($value)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s must return an iterable of providers; it returned "%s"',
                $subject,
                get_debug_type($value),
            ));
        }

        /** @var iterable<class-string|callable> */
        return $value;
    }
}
