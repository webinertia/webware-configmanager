<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Pattern;

use Psl\Type;
use Traversable;
use Webware\ConfigManager\Exception;

use function array_map;
use function get_debug_type;
use function is_array;
use function iterator_to_array;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

/**
 * Validates the regular expressions that map a requested service name to a config key.
 *
 * A pattern that is not a string, is empty, or does not compile used to be accepted and
 * then fail at match time, either as a PHP warning from preg_match() or as a TypeError
 * on a non-string element. Both are rejected here instead, at the point the caller
 * supplies the pattern, which is where the mistake actually is.
 *
 * @internal
 */
final class PatternValidator
{
    /**
     * Accept an array or Traversable of compilable, non-empty pattern strings.
     *
     * @return array<array-key, string>
     * @throws Exception\InvalidArgumentException
     */
    public static function validate(mixed $patterns): array
    {
        if ($patterns instanceof Traversable) {
            $patterns = iterator_to_array($patterns);
        }

        if (! is_array($patterns)) {
            throw new Exception\InvalidArgumentException('patterns must be array or Traversable');
        }

        // array_map with a single array preserves keys, and pattern order is precedence,
        // so the caller's ordering survives.
        return array_map(self::validateOne(...), $patterns);
    }

    /**
     * Accept only a non-empty string that compiles as a regular expression.
     *
     * `Type\non_empty_string()->matches()` is deliberate rather than `coerce()`: PSL
     * coercion would accept an integer and silently turn it into its string form, which
     * is worse than rejecting it, because the caller's mistake would survive.
     *
     * @throws Exception\InvalidArgumentException
     */
    public static function validateOne(mixed $pattern): string
    {
        if (! Type\non_empty_string()->matches($pattern)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Each pattern must be a non-empty string; received "%s"',
                get_debug_type($pattern),
            ));
        }

        if (! self::compiles($pattern)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Pattern "%s" is not a valid regular expression',
                $pattern,
            ));
        }

        return $pattern;
    }

    /**
     * Determine whether a pattern compiles, without letting the warning escape.
     *
     * An invalid pattern makes preg_match() return false and raise a warning. The handler
     * below absorbs it so the caller gets the package exception instead, which is also
     * what keeps the check usable under a fail-on-warning harness.
     */
    private static function compiles(string $pattern): bool
    {
        set_error_handler(static fn(): bool => true);

        try {
            return false !== preg_match(
                pattern: $pattern,
                subject: '',
            );
        } finally {
            restore_error_handler();
        }
    }
}
