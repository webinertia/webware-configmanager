<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Processor;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Processor\Token as TokenProcessor;
use WebwareTest\ConfigManager\DeprecatedAssertionTrait;

use function iterator_to_array;

/**
 * Majority of tests are in WebwareTest\ConfigManager\ProcessorTest; this class contains
 * tests covering new functionality and/or specific bugs.
 */
#[CoversClass(TokenProcessor::class)]
final class TokenTest extends TestCase
{
    use DeprecatedAssertionTrait;

    #[Test]
    public function testCanEnableKeyProcessingViaConstructorArgument()
    {
        $processor = new TokenProcessor([], '', '', true);
        static::assertAttributeSame(true, 'processKeys', $processor);
    }

    #[Test]
    public function testInvalidTokenRegistryIsRejected()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot use string as token registry.');

        new TokenProcessor()->setTokens('not a registry');
    }

    #[Test]
    public function testKeyProcessingDisabledByDefault()
    {
        $processor = new TokenProcessor();
        static::assertAttributeSame(false, 'processKeys', $processor);
    }

    #[Test]
    public function testPrefixAndSuffixAreExposed()
    {
        $processor = new TokenProcessor([], '##', '%%');

        static::assertSame('##', $processor->getPrefix());
        static::assertSame('%%', $processor->getSuffix());
    }

    #[Test]
    public function testReplacementMapIsCachedBetweenRuns()
    {
        $processor = new TokenProcessor(['TOKEN' => 'one'], '(', ')');

        static::assertSame('one', $processor->processValue('(TOKEN)'));
        static::assertSame('one', $processor->processValue('(TOKEN)'));
    }

    #[Test]
    public function testSetTokenRegistersASingleToken()
    {
        $processor = new TokenProcessor();
        $processor->setToken('TOKEN', 'replacement');

        static::assertSame(['TOKEN' => 'replacement'], $processor->getTokens());
    }

    #[Test]
    public function testTraversableValuesAreProcessedRecursively()
    {
        $processor = new TokenProcessor(['TOKEN' => 'replacement']);

        $result = $processor->processValue(new ArrayIterator(['TOKEN']));

        static::assertSame(['replacement'], iterator_to_array($result));
    }
}
