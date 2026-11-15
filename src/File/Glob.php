<?php

declare(strict_types=1);

namespace Webware\ConfigManager\File;

use function array_merge;
use function array_unique;
use function array_values;
use function glob;

/**
 * Matches a filesystem pattern, expanding brace groups in the order written.
 *
 * This replaces Laminas\Stdlib\Glob so the package does not depend on
 * laminas/laminas-stdlib. The ordering is the part that matters, and it is reproduced
 * exactly: brace alternatives are tried in the order written, each alternative keeps the
 * sorted order glob() gives it, and a path matched by more than one alternative is kept
 * only once. So `{{,*.}global,{,*.}local}.php` yields `global.php`, then every
 * `*.global.php` in name order, then `local.php`, then every `*.local.php`. The empty
 * alternative is written first inside each group, so the generic file is merged before
 * the named ones and any named file wins over it.
 *
 * @internal
 */
final class Glob
{
    /**
     * Expand the pattern and return every path that exists for it.
     *
     * @return list<string>
     */
    public static function match(string $pattern): array
    {
        $matches = [];

        foreach (BracePattern::expand($pattern) as $candidate) {
            // glob() documents a false return on failure, but nothing reachable produces
            // one: a pattern containing a null byte raises ValueError before the call, and
            // every other failure returns an empty array. Collapsing a false to an empty
            // list therefore cannot change a result, and it keeps the type honest without a
            // dead branch.
            $result    = glob($candidate);
            $matches[] = false === $result ? [] : $result;
        }

        return array_values(array_unique(array_merge(...$matches)));
    }
}
