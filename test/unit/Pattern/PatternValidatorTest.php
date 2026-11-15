<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Pattern;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Pattern\PatternValidator;

use function error_clear_last;
use function error_get_last;

#[CoversClass(PatternValidator::class)]
final class PatternValidatorTest extends TestCase
{
    #[Test]
    public function itAcceptsAListOfCompilablePatterns(): void
    {
        static::assertSame(
            ['#^a(.*)$#', '#^b(.*)$#'],
            PatternValidator::validate(['#^a(.*)$#', '#^b(.*)$#']),
        );
    }

    #[Test]
    public function itAcceptsATraversable(): void
    {
        static::assertSame(['#^a(.*)$#'], PatternValidator::validate(new ArrayIterator(['#^a(.*)$#'])));
    }

    #[Test]
    public function itPreservesKeysAndOrderBecauseOrderIsPrecedence(): void
    {
        static::assertSame(
            ['first' => '#^a(.*)$#', 7 => '#^b(.*)$#'],
            PatternValidator::validate(['first' => '#^a(.*)$#', 7 => '#^b(.*)$#']),
        );
    }

    #[Test]
    public function itRejectsAnEmptyPattern(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Each pattern must be a non-empty string; received "string"');

        PatternValidator::validate(['']);
    }

    #[Test]
    public function itRejectsAnObjectElement(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Each pattern must be a non-empty string; received "stdClass"');

        PatternValidator::validate([new stdClass()]);
    }

    #[Test]
    public function itRejectsANonArrayNonTraversable(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('patterns must be array or Traversable');

        PatternValidator::validate('not-a-pattern-list');
    }

    #[Test]
    public function itRejectsANonStringElementRatherThanCoercingIt(): void
    {
        // PSL coercion would accept 12345 and turn it into '12345'. That would preserve
        // the caller's mistake, so matches() is used instead.
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Each pattern must be a non-empty string; received "int"');

        PatternValidator::validate(['#^a(.*)$#', 12_345]);
    }

    #[Test]
    public function itRejectsANullElement(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Each pattern must be a non-empty string; received "null"');

        PatternValidator::validate([null]);
    }

    #[Test]
    public function itRejectsAPatternThatDoesNotCompileWithoutLeakingTheWarning(): void
    {
        // preg_match() returns false and warns for an invalid pattern. The warning is
        // absorbed so the caller sees the package exception, and so this assertion can
        // run under failOnWarning.
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Pattern "not-a-regex" is not a valid regular expression');

        PatternValidator::validate(['not-a-regex']);
    }

    #[Test]
    public function itRejectsAPatternWithAnUnbalancedDelimiter(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Pattern "#unclosed" is not a valid regular expression');

        PatternValidator::validate(['#unclosed']);
    }

    #[Test]
    public function validateOneAcceptsASingleValidPattern(): void
    {
        static::assertSame('#^a(.*)$#', PatternValidator::validateOne('#^a(.*)$#'));
    }

    #[Test]
    public function validateOneRejectsASingleInvalidPattern(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);

        PatternValidator::validateOne(0);
    }

    #[Test]
    public function validatingAnInvalidPatternDoesNotLetTheWarningEscape(): void
    {
        // A handler returning true suppresses PHP's internal handler, so the warning is
        // never recorded. Returning false would let it through and it would appear in
        // error_get_last(), which is what this asserts against.
        error_clear_last();

        $thrown = null;

        try {
            PatternValidator::validate(['#unclosed']);
        } catch (Exception\InvalidArgumentException $exception) {
            $thrown = $exception;
        }

        static::assertInstanceOf(Exception\InvalidArgumentException::class, $thrown);
        static::assertNull(error_get_last());
    }
}
