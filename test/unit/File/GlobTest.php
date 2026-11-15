<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\File\Glob;

use function array_map;
use function basename;
use function realpath;

use const DIRECTORY_SEPARATOR;

#[CoversClass(Glob::class)]
final class GlobTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../Provider/TestAssets/Php';

    #[Test]
    public function aLiteralNameNeedsNoBrace(): void
    {
        static::assertSame(['global.php'], $this->basenames('global.php'));
    }

    #[Test]
    public function anEarlierAlternativeKeepsItsSortedOrder(): void
    {
        static::assertSame(
            ['first.global.php', 'first.local.php', 'global.php', 'scalar.php', 'second.global.php'],
            $this->basenames('*.php'),
        );
    }

    #[Test]
    public function aPathMatchedTwiceIsReportedOnce(): void
    {
        static::assertSame(['global.php'], $this->basenames('{global,global}.php'));
    }

    #[Test]
    public function aPatternThatMatchesNothingReturnsAnEmptyList(): void
    {
        static::assertSame([], Glob::match(self::FIXTURES . '/{nothing,else}.php'));
    }

    #[Test]
    public function filesAreOrderedByLayerSoALaterLayerWins(): void
    {
        // This is the precedence the aggregator's PhpFileProvider relies on, and the
        // reason the pattern is not simply handed to glob(). The empty alternative comes
        // first inside each group, so a named file overrides the generic one.
        static::assertSame(
            ['global.php', 'first.global.php', 'second.global.php', 'first.local.php'],
            $this->basenames('{{,*.}global,{,*.}local}.php'),
        );
    }

    /** @return list<string> */
    private function basenames(string $pattern): array
    {
        $directory = realpath(self::FIXTURES);

        static::assertIsString($directory);

        return array_map(
            basename(...),
            Glob::match($directory . DIRECTORY_SEPARATOR . $pattern),
        );
    }
}
