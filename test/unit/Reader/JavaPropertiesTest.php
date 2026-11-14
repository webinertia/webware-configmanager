<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Reader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader\JavaProperties;

#[CoversClass(JavaProperties::class)]
final class JavaPropertiesTest extends AbstractReaderTestCase
{
    /**
     * @return array
     */
    public static function invalidDelimiters()
    {
        return [
            'null'         => [null],
            'true'         => [true],
            'false'        => [false],
            'zero'         => [0],
            'int'          => [1],
            'zero-float'   => [0.0],
            'float'        => [1.1],
            'empty-string' => [''],
            'array'        => [[':']],
            'object'       => [(object) ['delimiter' => ':']],
        ];
    }

    #[Test]
    public function testAllowsSpecifyingAlternateKeyValueDelimiter()
    {
        $reader = new JavaProperties('=');

        $arrayJavaProperties = $reader->fromFile($this->getTestAssetPath('alternate-delimiter'));

        static::assertNotEmpty($arrayJavaProperties);
        static::assertSame($arrayJavaProperties['single.line'], 'test');
        static::assertSame($arrayJavaProperties['multiple'], 'line test');
    }

    #[Test]
    public function testFromFile()
    {
        $arrayJavaProperties = $this->reader->fromFile($this->getTestAssetPath('include-target'));

        static::assertNotEmpty($arrayJavaProperties);
        static::assertSame($arrayJavaProperties['single.line'], 'test');
        static::assertSame($arrayJavaProperties['multiple'], 'line test');
    }

    #[Test]
    public function testFromString()
    {
        $javaProperties = <<<'ASSET'
            #comment
            !comment
            single.line:test
            multiple:line \
            test
            ASSET;

        $arrayJavaProperties = $this->reader->fromString($javaProperties);

        static::assertNotEmpty($arrayJavaProperties);
        static::assertSame($arrayJavaProperties['single.line'], 'test');
        static::assertSame($arrayJavaProperties['multiple'], 'line test');
    }

    #[Test]
    public function testIncludeAsElement()
    {
        $arrayJavaProperties = $this->reader->fromFile($this->getTestAssetPath('include-base'));

        static::assertNotEmpty($arrayJavaProperties);
        static::assertSame($arrayJavaProperties['single.line'], 'test');
        static::assertSame($arrayJavaProperties['multiple'], 'line test');
    }

    /**
     * @param mixed $delimiter
     */
    #[DataProvider('invalidDelimiters')]
    #[Test]
    public function testInvalidDelimiterValuesResultInExceptions($delimiter)
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        new JavaProperties($delimiter);
    }

    #[Test]
    public function testInvalidIncludeInString()
    {
        $javaProperties = '@include:fail.properties';

        $expectedErrorMessage = 'Cannot process @include statement for a string';

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage($expectedErrorMessage);
        $this->reader->fromString($javaProperties);
    }

    #[Test]
    public function testProvidesOptionToTrimWhitespaceFromKeysAndValues()
    {
        $reader              = new JavaProperties(JavaProperties::DELIMITER_DEFAULT, JavaProperties::WHITESPACE_TRIM);
        $arrayJavaProperties = $reader->fromFile($this->getTestAssetPath('key-value-whitespace'));

        static::assertNotEmpty($arrayJavaProperties);
        static::assertSame($arrayJavaProperties['single.line'], 'test');
        static::assertSame($arrayJavaProperties['multiple'], 'line test');
    }

    /**
     * getTestAssetPath(): defined by AbstractReaderTestCase.
     *
     * @see    AbstractReaderTestCase::getTestAssetPath()
     *
     * @param  string $name
     * @return string
     */
    protected function getTestAssetPath($name)
    {
        return __DIR__ . '/TestAssets/JavaProperties/' . $name . '.properties';
    }

    protected function setUp(): void
    {
        $this->reader = new JavaProperties();
    }
}
