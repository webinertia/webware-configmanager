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
    public function canEnableKeyProcessingViaConstructorArgument()
    {
        $processor = new TokenProcessor([], '', '', true);
        static::assertAttributeSame(
            value   : true,
            property: 'processKeys',
            instance: $processor,
        );
    }

    #[Test]
    public function invalidTokenRegistryIsRejected()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Cannot use string as token registry.');

        new TokenProcessor()->setTokens('not a registry');
    }

    #[Test]
    public function keyProcessingDisabledByDefault()
    {
        $processor = new TokenProcessor();
        static::assertAttributeSame(
            value   : false,
            property: 'processKeys',
            instance: $processor,
        );
    }

    #[Test]
    public function prefixAndSuffixAreExposed()
    {
        $processor = new TokenProcessor([], '##', '%%');

        static::assertSame('##', $processor->prefix);
        static::assertSame('%%', $processor->suffix);
    }

    #[Test]
    public function replacementMapIsCachedBetweenRuns()
    {
        $processor = new TokenProcessor(['TOKEN' => 'one'], '(', ')');

        static::assertSame('one', $processor->processValue('(TOKEN)'));
        static::assertSame('one', $processor->processValue('(TOKEN)'));
    }

    #[Test]
    public function setTokenRegistersASingleToken()
    {
        $processor = new TokenProcessor();
        $processor->setToken(
            token: 'TOKEN',
            value: 'replacement',
        );

        static::assertSame(['TOKEN' => 'replacement'], $processor->tokens);
    }

    #[Test]
    public function traversableValuesAreProcessedRecursively()
    {
        // @mago-expect lint:no-literal-password
        $processor = new TokenProcessor(['TOKEN' => 'replacement']);

        $result = $processor->processValue(new ArrayIterator(['TOKEN']));

        static::assertSame(['replacement'], iterator_to_array($result));
    }
}
