<?php

declare(strict_types=1);

namespace Webware\ConfigManager\File;

use function strlen;
use function strpos;
use function substr;

/**
 * Rewrites a brace pattern as one brace-free pattern per alternative.
 *
 * The expansion is ordered rather than sorted: alternatives are produced in the order
 * they are written, leftmost group first, and nesting is preserved. That order is the
 * whole reason the class exists, because layered configuration precedence depends on it.
 *
 * A backslash is a path separator, never an escape, so a Windows path expands unchanged
 * and a literal brace in a directory or file name is unsupported.
 *
 * @internal
 */
final class BracePattern
{
    /**
     * @return list<string>
     */
    public static function expand(string $pattern): array
    {
        $open = self::openingBrace($pattern);

        if (null === $open) {
            return [$pattern];
        }

        $close = self::closingBrace($pattern, $open);

        if (null === $close) {
            // An unbalanced brace does not open a group. Leaving it in place lets the
            // pattern matcher treat it literally, which is what the native function does.
            return [$pattern];
        }

        $prefix = substr(
            string: $pattern,
            offset: 0,
            length: $open,
        );
        $suffix = substr(
            string: $pattern,
            offset: $close + 1,
        );
        $body = substr(
            string: $pattern,
            offset: $open + 1,
            length: $close - $open - 1,
        );
        $expanded = [];

        foreach (self::split($body) as $alternative) {
            foreach (self::expand($prefix . $alternative . $suffix) as $candidate) {
                $expanded[] = $candidate;
            }
        }

        return $expanded;
    }

    /** @return int|null Offset of the brace closing the group opened at $open. */
    private static function closingBrace(string $pattern, int $open): ?int
    {
        $length = strlen($pattern);
        $depth  = 0;

        for ($offset = $open + 1; $offset < $length; $offset++) {
            if ('{' === $pattern[$offset]) {
                $depth++;
                continue;
            }

            if ('}' !== $pattern[$offset]) {
                continue;
            }

            if (0 === $depth) {
                return $offset;
            }

            $depth--;
        }

        return null;
    }

    /** @return int|null Offset of the first opening brace, if there is one. */
    private static function openingBrace(string $pattern): ?int
    {
        $offset = strpos(
            haystack: $pattern,
            needle  : '{',
        );

        return false === $offset ? null : $offset;
    }

    /**
     * Split a group body on its top-level commas.
     *
     * A comma inside a nested group belongs to that group, so the nesting depth is
     * tracked rather than splitting on every comma. The braces themselves are kept,
     * because a nested group is expanded by the recursive call.
     *
     * @return list<string>
     */
    private static function split(string $body): array
    {
        $length       = strlen($body);
        $depth        = 0;
        $alternatives = [];
        $current      = '';

        for ($offset = 0; $offset < $length; $offset++) {
            $character = $body[$offset];

            if (',' === $character && 0 === $depth) {
                $alternatives[] = $current;
                $current        = '';
                continue;
            }

            if ('{' === $character) {
                $depth++;
            }

            if ('}' === $character) {
                $depth--;
            }

            $current .= $character;
        }

        $alternatives[] = $current;

        return $alternatives;
    }
}
