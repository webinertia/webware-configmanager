<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use Laminas\Filter\PregReplace;
use Laminas\Filter\StringToLower;
use Laminas\Filter\StringToUpper;
use Laminas\I18n\Translator\LoaderPluginManager;
use Laminas\I18n\Translator\TranslationCollector\FileListCollector;
use Laminas\I18n\Translator\Translator;
use Laminas\I18n\Translator\Value\TranslationFile;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Processor\Constant as ConstantProcessor;
use Webware\ConfigManager\Processor\Filter as FilterProcessor;
use Webware\ConfigManager\Processor\Queue;
use Webware\ConfigManager\Processor\Token as TokenProcessor;
use Webware\ConfigManager\Processor\Translator as TranslatorProcessor;

use function define;
use function defined;
use function gettype;
use function realpath;

use const PHP_VERSION;

#[CoversClass(ConstantProcessor::class)]
#[CoversClass(FilterProcessor::class)]
#[CoversClass(Queue::class)]
#[CoversClass(TokenProcessor::class)]
#[CoversClass(TranslatorProcessor::class)]
final class ProcessorTest extends TestCase
{
    /** @var array */
    protected $nested;
    /** @var array */
    protected $tokenBare;
    /** @var array */
    protected $tokenPrefix;
    /** @var array */
    protected $tokenSuffix;
    /** @var array */
    protected $tokenSurround;
    /** @var array */
    protected $tokenSurroundMixed;
    /** @var string[][][] */
    protected $translatorData;
    /** @var false|string */
    protected $translatorFile;
    /** @var array */
    protected $userConstants;
    /** @var array */
    protected $phpConstants;
    /** @var array */
    protected $filter;

    public static function setUpBeforeClass(): void
    {
        if (! defined('SOME_USERLAND_CONSTANT')) {
            define(
                constant_name: 'SOME_USERLAND_CONSTANT',
                value        : 'some constant value',
            );
        }
    }

    #[Test]
    public function testAddInvalidToken()
    {
        $processor = new TokenProcessor();
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot use ' . gettype([]) . ' as token name.');
        $processor->addToken([], 'bar');
    }

    #[Test]
    public function testBareTokenPost()
    {
        $config    = new Config($this->tokenBare, true);
        $processor = new TokenProcessor();
        $processor->addToken('BARETOKEN', 'some replaced value');
        $processor->process($config);

        static::assertEquals(['BARETOKEN' => 'some replaced value'], $processor->getTokens());
        static::assertSame('some replaced value', $config->simple);
        static::assertSame('some text with some replaced value inside', $config->inside);
        static::assertSame('some replaced value', $config->nested->simple);
        static::assertSame('some text with some replaced value inside', $config->nested->inside);
    }

    #[Test]
    public function testFilter()
    {
        $config    = new Config($this->filter, true);
        $filter    = new StringToLower();
        $processor = new FilterProcessor($filter);

        static::assertInstanceOf(StringToLower::class, $processor->getFilter());
        $processor->process($config);

        static::assertSame('some mixedcase value', $config->simple);
        static::assertSame('other mixed case value', $config->nested->simple);
    }

    #[Test]
    public function testFilterReadOnly()
    {
        $config    = new Config($this->filter, false);
        $filter    = new StringToLower();
        $processor = new FilterProcessor($filter);

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot process config because it is read-only');
        $processor->process($config);
    }

    #[Test]
    public function testFilterValue()
    {
        $filter    = new StringToLower();
        $processor = new FilterProcessor($filter);

        $value = 'TEST';
        static::assertSame('test', $processor->processValue($value));
    }

    #[Test]
    public function testIgnoresEmptyStringReplacement()
    {
        $config    = new Config(['foo' => 'bar'], true);
        $processor = new TokenProcessor(['' => 'invalid']);

        $processor->process($config);

        static::assertSame('bar', $config['foo']);
    }

    #[Test]
    public function testPHPConstants()
    {
        $config    = new Config($this->phpConstants, true);
        $processor = new ConstantProcessor(false);
        $processor->process($config);

        static::assertEquals(PHP_VERSION, $config->phpVersion);
        static::assertEquals('Current PHP version is: ' . PHP_VERSION, $config->phpVersionInside);
        static::assertEquals(PHP_VERSION, $config->nested->phpVersion);
        static::assertEquals('Current PHP version is: ' . PHP_VERSION, $config->nested->phpVersionInside);
    }

    #[Test]
    public function testProcessorsQueue()
    {
        $processor1 = new TokenProcessor();
        $processor2 = new TokenProcessor();
        $queue      = new Queue();
        $queue->insert($processor1);
        $queue->insert($processor2);

        static::assertInstanceOf(Queue::class, $queue);
        static::assertSame(2, $queue->count());
        static::assertTrue($queue->contains($processor1));
        static::assertTrue($queue->contains($processor2));
    }

    #[Test]
    public function testQueueFIFO()
    {
        $config         = new Config($this->filter, true);
        $lower          = new StringToLower();
        $upper          = new StringToUpper();
        $lowerProcessor = new FilterProcessor($lower);
        $upperProcessor = new FilterProcessor($upper);

        /**
         * Default queue order (FIFO)
         */
        $queue = new Queue();
        $queue->insert($upperProcessor);
        $queue->insert($lowerProcessor);
        $queue->process($config);

        static::assertSame('some mixedcase value', $config->simple);
        static::assertSame('other mixed case value', $config->nested->simple);
    }

    #[Test]
    public function testQueuePriorities()
    {
        $config           = new Config($this->filter, 1);
        $lower            = new StringToLower();
        $upper            = new StringToUpper();
        $replace          = new PregReplace(['pattern' => '/[a-z]/']);
        $lowerProcessor   = new FilterProcessor($lower);
        $upperProcessor   = new FilterProcessor($upper);
        $replaceProcessor = new FilterProcessor($replace);
        $queue            = new Queue();

        /**
         * Insert lower case filter with higher priority
         */
        $queue->insert($upperProcessor, 10);
        $queue->insert($lowerProcessor, 1000);

        $config->simple = 'some MixedCase VALue';
        $queue->process($config);
        static::assertSame('SOME MIXEDCASE VALUE', $config->simple);

        /**
         * Add even higher priority replace processor that will remove all lowercase letters
         */
        $queue->insert($replaceProcessor, 10_000);
        $config->newValue = 'THIRD mixed CASE value';
        $queue->process($config);
        static::assertSame('THIRD  CASE ', $config->newValue);
    }

    #[Test]
    public function testQueueReadOnly()
    {
        $config         = new Config($this->filter, false);
        $lower          = new StringToLower();
        $lowerProcessor = new FilterProcessor($lower);

        /**
         * Default queue order (FIFO)
         */
        $queue = new Queue();
        $queue->insert($lowerProcessor);

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot process config because it is read-only');
        $queue->process($config);
    }

    #[Test]
    public function testQueueSingleValue()
    {
        $lower          = new StringToLower();
        $upper          = new StringToUpper();
        $lowerProcessor = new FilterProcessor($lower);
        $upperProcessor = new FilterProcessor($upper);

        /**
         * Default queue order (FIFO)
         */
        $queue = new Queue();
        $queue->insert($upperProcessor);
        $queue->insert($lowerProcessor);

        $data = 'TeSt';
        static::assertSame('test', $queue->processValue($data));
    }

    #[Test]
    public function testSingleValueToken()
    {
        $processor = new TokenProcessor();
        $processor->addToken('BARETOKEN', 'test');
        $data = 'BARETOKEN';
        $out  = $processor->processValue($data);
        static::assertSame($out, 'test');
    }

    #[Test]
    public function testTokenChangeParams()
    {
        $config    = new Config($this->tokenSurroundMixed, true);
        $processor = new TokenProcessor(['TOKEN' => 'some replaced value'], '##', '##');
        $processor->process($config);
        static::assertSame('some replaced value', $config->simple);
        static::assertSame('## some text with some replaced value inside ##', $config->inside);
        static::assertSame('@@TOKEN@@', $config->nested->simple);
        static::assertSame('@@ some text with @@TOKEN@@ inside @@', $config->nested->inside);

        /**
         * Now change prefix and suffix on the processor
         */
        $processor->setPrefix('@@');
        $processor->setSuffix('@@');

        /**
         * Parse the config again
         */
        $processor->process($config);

        static::assertSame('some replaced value', $config->simple);
        static::assertSame('## some text with some replaced value inside ##', $config->inside);
        static::assertSame('some replaced value', $config->nested->simple);
        static::assertSame('@@ some text with some replaced value inside @@', $config->nested->inside);
    }

    #[Test]
    public function testTokenChangeParamsReplacesInNumerics()
    {
        $config = new Config(
            [
                'foo'          => 'bar1',
                'trueBoolKey'  => true,
                'falseBoolKey' => false,
                'intKey'       => 123,
                'floatKey'     => 123.456,
                'doubleKey'    => 456.789,
            ],
            true,
        );

        $processor = new TokenProcessor(['1' => 'R', '9' => 'R']);

        $processor->process($config);

        static::assertSame('R', $config['trueBoolKey']);
        static::assertSame('barR', $config['foo']);
        static::assertFalse($config['falseBoolKey']);
        static::assertSame('R23', $config['intKey']);
        static::assertSame('R23.456', $config['floatKey']);
        static::assertSame('456.78R', $config['doubleKey']);
    }

    #[Test]
    public function testTokenChangeParamsRetainsType()
    {
        $config = new Config(
            [
                'trueBoolKey'  => true,
                'falseBoolKey' => false,
                'intKey'       => 123,
                'floatKey'     => 123.456,
                'doubleKey'    => 456.789,
            ],
            true,
        );

        $processor = new TokenProcessor();

        $processor->process($config);

        static::assertTrue($config['trueBoolKey']);
        static::assertFalse($config['falseBoolKey']);
        static::assertSame(123, $config['intKey']);
        static::assertSame(123.456, $config['floatKey']);
        static::assertSame(456.789, $config['doubleKey']);
    }

    #[Test]
    public function testTokenPrefix()
    {
        $config    = new Config($this->tokenPrefix, true);
        $processor = new TokenProcessor(['TOKEN' => 'some replaced value'], '::');
        $processor->process($config);

        static::assertSame('some replaced value', $config->simple);
        static::assertSame(':: some text with some replaced value inside ::', $config->inside);
        static::assertSame('some replaced value', $config->nested->simple);
        static::assertSame(':: some text with some replaced value inside ::', $config->nested->inside);
    }

    #[Test]
    public function testTokenReadOnly()
    {
        $config    = new Config($this->tokenBare, false);
        $processor = new TokenProcessor();
        $processor->addToken('BARETOKEN', 'some replaced value');

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot process config because it is read-only');
        $processor->process($config);
    }

    #[Test]
    public function testTokenSuffix()
    {
        $config    = new Config($this->tokenSuffix, true);
        $processor = new TokenProcessor(['TOKEN' => 'some replaced value'], '', '::');
        $processor->process($config);

        static::assertSame('some replaced value', $config->simple);
        static::assertSame(':: some text with some replaced value inside ::', $config->inside);
        static::assertSame('some replaced value', $config->nested->simple);
        static::assertSame(':: some text with some replaced value inside ::', $config->nested->inside);
    }

    #[Test]
    public function testTokenSurround()
    {
        $config    = new Config($this->tokenSurround, true);
        $processor = new TokenProcessor(['TOKEN' => 'some replaced value'], '##', '##');
        $processor->process($config);

        static::assertSame('some replaced value', $config->simple);
        static::assertSame('## some text with some replaced value inside ##', $config->inside);
        static::assertSame('some replaced value', $config->nested->simple);
        static::assertSame('## some text with some replaced value inside ##', $config->nested->inside);
    }

    #[Test]
    public function testTranslator()
    {
        $config     = new Config($this->translatorData, true);
        $translator = $this->createTranslator();
        $processor  = new TranslatorProcessor($translator);

        static::assertSame($translator, $processor->getTranslator());
        static::assertSame('default', $processor->getTextDomain());
        static::assertNull($processor->getLocale());

        $processor->process($config);

        static::assertSame('oneDog', $config->pages[0]->id);
        static::assertSame('ein Hund', $config->pages[0]->label);
        static::assertSame('twoDogs', $config->pages[1]->id);
        static::assertSame('zwei Hunde', $config->pages[1]->label);
    }

    #[Test]
    public function testTranslatorProcessValueLeavesNonStringValuesUntouched()
    {
        $translator = $this->createTranslator();
        $processor  = new TranslatorProcessor($translator);

        static::assertSame(42, $processor->processValue(42));
        static::assertSame('', $processor->processValue(''));
        static::assertNull($processor->processValue(null));
    }

    #[Test]
    public function testTranslatorReadOnly()
    {
        $config     = new Config($this->translatorData, false);
        $translator = $this->createTranslator();
        $processor  = new TranslatorProcessor($translator);

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot process config because it is read-only');
        $processor->process($config);
    }

    #[Test]
    public function testTranslatorRejectsEmptyLocale()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('locale must be a non-empty string or null');

        new TranslatorProcessor($this->createTranslator())->setLocale('');
    }

    #[Test]
    public function testTranslatorRejectsEmptyTextDomain()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('textDomain must be a non-empty string');

        new TranslatorProcessor($this->createTranslator())->setTextDomain('');
    }

    #[Test]
    public function testTranslatorSingleValue()
    {
        $translator = $this->createTranslator();
        $processor  = new TranslatorProcessor($translator);

        static::assertSame('ein Hund', $processor->processValue('one dog'));
    }

    #[Test]
    public function testUserConstants()
    {
        $config    = new Config($this->userConstants, true);
        $processor = new ConstantProcessor(false);
        $processor->process($config);

        $tokens = $processor->getTokens();
        static::assertIsArray($tokens);
        static::assertArrayHasKey('SOME_USERLAND_CONSTANT', $tokens);
        static::assertFalse($processor->getUserOnly());

        static::assertSame('some constant value', $config->simple);
        static::assertSame('some text with some constant value inside', $config->inside);
        static::assertSame('some constant value', $config->nested->simple);
        static::assertSame('some text with some constant value inside', $config->nested->inside);
    }

    #[Test]
    public function testUserOnlyConstants()
    {
        $config    = new Config($this->userConstants, true);
        $processor = new ConstantProcessor();
        $processor->process($config);

        $tokens = $processor->getTokens();

        static::assertIsArray($tokens);
        static::assertArrayHasKey('SOME_USERLAND_CONSTANT', $tokens);
        static::assertTrue($processor->getUserOnly());

        static::assertSame('some constant value', $config->simple);
        static::assertSame('some text with some constant value inside', $config->inside);
        static::assertSame('some constant value', $config->nested->simple);
        static::assertSame('some text with some constant value inside', $config->nested->inside);
    }

    protected function setUp(): void
    {
        // Arrays representing common config configurations
        $this->nested = [
            'a' => 1,
            'b' => 2,
            'c' => [
                'ca' => 3,
                'cb' => 4,
                'cc' => 5,
                'cd' => [
                    'cda' => 6,
                    'cdb' => 7,
                ],
            ],
            'd' => [
                'da' => 8,
                'db' => 9,
            ],
            'e' => 10,
        ];

        $this->tokenBare = [
            'simple' => 'BARETOKEN',
            'inside' => 'some text with BARETOKEN inside',
            'nested' => [
                'simple' => 'BARETOKEN',
                'inside' => 'some text with BARETOKEN inside',
            ],
        ];

        $this->tokenPrefix = [
            'simple' => '::TOKEN',
            'inside' => ':: some text with ::TOKEN inside ::',
            'nested' => [
                'simple' => '::TOKEN',
                'inside' => ':: some text with ::TOKEN inside ::',
            ],
        ];

        $this->tokenSuffix = [
            'simple' => 'TOKEN::',
            'inside' => ':: some text with TOKEN:: inside ::',
            'nested' => [
                'simple' => 'TOKEN::',
                'inside' => ':: some text with TOKEN:: inside ::',
            ],
        ];

        $this->tokenSurround = [
            'simple' => '##TOKEN##',
            'inside' => '## some text with ##TOKEN## inside ##',
            'nested' => [
                'simple' => '##TOKEN##',
                'inside' => '## some text with ##TOKEN## inside ##',
            ],
        ];

        $this->tokenSurroundMixed = [
            'simple' => '##TOKEN##',
            'inside' => '## some text with ##TOKEN## inside ##',
            'nested' => [
                'simple' => '@@TOKEN@@',
                'inside' => '@@ some text with @@TOKEN@@ inside @@',
            ],
        ];

        $this->translatorData = [
            'pages' => [
                [
                    'id'    => 'oneDog',
                    'label' => 'one dog',
                    'route' => 'app-one-dog',
                ],
                [
                    'id'    => 'twoDogs',
                    'label' => 'two dogs',
                    'route' => 'app-two-dogs',
                ],
            ],
        ];

        $this->translatorFile = realpath(__DIR__ . '/_files/translations-de_DE.php');

        $this->filter = [
            'simple' => 'some MixedCase VALue',
            'nested' => [
                'simple' => 'OTHER mixed Case Value',
            ],
        ];

        $this->userConstants = [
            'simple' => 'SOME_USERLAND_CONSTANT',
            'inside' => 'some text with SOME_USERLAND_CONSTANT inside',
            'nested' => [
                'simple' => 'SOME_USERLAND_CONSTANT',
                'inside' => 'some text with SOME_USERLAND_CONSTANT inside',
            ],
        ];

        $this->phpConstants = [
            'phpVersion'       => 'PHP_VERSION',
            'phpVersionInside' => 'Current PHP version is: PHP_VERSION',
            'nested'           => [
                'phpVersion'       => 'PHP_VERSION',
                'phpVersionInside' => 'Current PHP version is: PHP_VERSION',
            ],
        ];
    }

    private function createTranslator(): Translator
    {
        $loader    = new LoaderPluginManager(new ServiceManager());
        $collector = new FileListCollector(
            [
                'default' => [
                    'de_DE' => [
                        new TranslationFile('phparray', $this->translatorFile, 'de_DE', 'default'),
                    ],
                ],
            ],
            $loader,
        );

        return new Translator($collector, 'de_DE');
    }
}
