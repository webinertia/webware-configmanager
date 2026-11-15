<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Processor\Constant as ConstantProcessor;
use WebwareTest\ConfigManager\DeprecatedAssertionTrait;

use function constant;
use function define;
use function defined;

#[CoversClass(ConstantProcessor::class)]
final class ConstantTest extends TestCase
{
    use DeprecatedAssertionTrait;

    public const CONFIG_TEST = 'config';

    /**
     * @return array
     */
    public static function constantProvider()
    {
        if (! defined('LAMINAS_CONFIG_PROCESSOR_CONSTANT_TEST')) {
            define(
                constant_name: 'LAMINAS_CONFIG_PROCESSOR_CONSTANT_TEST',
                value        : 'test-key',
            );
        }

        // @codingStandardsIgnoreStart
        //                                    constantString,                        constantValue
        return [
            'constant'                    => [
                'LAMINAS_CONFIG_PROCESSOR_CONSTANT_TEST',
                constant('LAMINAS_CONFIG_PROCESSOR_CONSTANT_TEST'),
            ],
            'class-constant'              => [__CLASS__ . '::CONFIG_TEST', self::CONFIG_TEST],
            'class-pseudo-constant'       => [__CLASS__ . '::class', self::class],
            'class-pseudo-constant-upper' => [__CLASS__ . '::CLASS', self::class],
        ];

        // @codingStandardsIgnoreEnd
    }

    #[Test]
    public function aClassPseudoConstantNamingAnUnknownClassIsReturnedVerbatim()
    {
        // PHP raises an error if ::class notation names a class that does not exist and
        // is then used as a constant, so the value is handed back untouched.
        $config = new Config(['test' => 'Missing\Klass::class'], true);

        new ConstantProcessor()->process($config);

        static::assertSame('Missing\Klass::class', $config->get('test'));
    }

    #[Test]
    public function aDoubleColonThatIsNotAClassConstantIsLeftToTheParentProcessor()
    {
        // "::" is present so the class-constant code path is entered, but the string is
        // neither a defined constant nor ::class notation, so it falls through unchanged.
        $config = new Config(['test' => 'Missing\Klass::value'], true);

        new ConstantProcessor()->process($config);

        static::assertSame('Missing\Klass::value', $config->get('test'));
    }

    #[Test]
    public function canEnableKeyProcessingViaConstructorArgument()
    {
        $processor = new ConstantProcessor(true, '', '', true);
        static::assertAttributeSame(
            value   : true,
            property: 'processKeys',
            instance: $processor,
        );
    }

    /**
     * @param string $constantString
     * @param string $constantValue
     */
    #[DataProvider('constantProvider')]
    #[Test]
    public function canProcessConstantValuesInKeys($constantString, $constantValue)
    {
        $config    = new Config([$constantString => 'value'], true);
        $processor = new ConstantProcessor();
        $processor->enableKeyProcessing();
        $processor->process($config);

        static::assertSame('value', $config->get($constantValue));
        static::assertNotSame('value', $config->get($constantString));
    }

    /**
     * @param string $constantString
     * @param string $constantValue
     */
    #[DataProvider('constantProvider')]
    #[Test]
    public function canResolveConstantValues($constantString, $constantValue)
    {
        $config = new Config(['test' => $constantString], true);

        $processor = new ConstantProcessor();
        $processor->process($config);

        static::assertEquals($constantValue, $config->get('test'));
    }

    #[Test]
    public function keyProcessingDisabledByDefault()
    {
        $processor = new ConstantProcessor();
        static::assertAttributeSame(
            value   : false,
            property: 'processKeys',
            instance: $processor,
        );
    }

    /**
     * @param string $constantString
     * @param string $constantValue
     */
    #[DataProvider('constantProvider')]
    #[Test]
    public function willNotProcessConstantValuesInKeysByDefault($constantString, $constantValue)
    {
        $config    = new Config([$constantString => 'value'], true);
        $processor = new ConstantProcessor();
        $processor->process($config);

        static::assertNotSame('value', $config->get($constantValue));
        static::assertSame('value', $config->get($constantString));
    }
}
