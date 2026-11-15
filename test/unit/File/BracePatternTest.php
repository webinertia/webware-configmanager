<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\File\BracePattern;

#[CoversClass(BracePattern::class)]
final class BracePatternTest extends TestCase
{
    #[Test]
    public function aBraceAtTheEndOfThePatternIsNotAGroup(): void
    {
        static::assertSame(['abc{'], BracePattern::expand('abc{'));
    }

    #[Test]
    public function aCommaInsideANestedGroupDoesNotSplitTheOuterGroup(): void
    {
        static::assertSame(
            ['a1', 'a2', 'b1', 'b2'],
            BracePattern::expand('{a{1,2},b{1,2}}'),
        );
    }

    #[Test]
    public function anEmptyAlternativeYieldsThePatternWithoutIt(): void
    {
        static::assertSame(['x.php', 'xy.php'], BracePattern::expand('x{,y}.php'));
    }

    #[Test]
    public function aNestedGroupExpandsInTheOrderWritten(): void
    {
        // The order is the contract: every *.global alternative is tried before the bare
        // global one, and every local alternative after both, so a later layer wins.
        static::assertSame(
            [
                '/config/autoload/global.php',
                '/config/autoload/a.global.php',
                '/config/autoload/local.php',
                '/config/autoload/a.local.php',
            ],
            BracePattern::expand('/config/autoload/{{,a.}global,{,a.}local}.php'),
        );
    }

    #[Test]
    public function anUnbalancedBraceIsLeftAlone(): void
    {
        // glob() treats an unmatched brace literally, so stripping it here would change
        // which files the caller matches.
        static::assertSame(['/config/{oops.php'], BracePattern::expand('/config/{oops.php'));
    }

    #[Test]
    public function aPatternWithoutABraceIsReturnedUnchanged(): void
    {
        static::assertSame(['/config/config.php'], BracePattern::expand('/config/config.php'));
    }
}
