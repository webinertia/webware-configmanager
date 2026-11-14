<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception as JavaPropertiesException;
use Webware\ConfigManager\Reader\JavaProperties as JavaPropertiesReader;
use Webware\ConfigManager\Writer\JavaProperties as JavaPropertiesWriter;

#[CoversClass(JavaPropertiesWriter::class)]
final class JavaPropertiesTest extends AbstractWriterTestCase
{
    #[Test]
    public function testConstructorRejectsEmptyDelimiter()
    {
        $this->expectException(JavaPropertiesException\InvalidArgumentException::class);

        new JavaPropertiesWriter('');
    }

    #[Test]
    public function testConstructorRejectsInvalidDelimiterValues()
    {
        $this->expectException(JavaPropertiesException\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid delimiter of type "NULL"');

        new JavaPropertiesWriter(null);
    }

    #[Test]
    public function testNonScalarValuesAreRejected()
    {
        $this->expectException(JavaPropertiesException\UnprocessableConfigException::class);
        $this->expectExceptionMessage('can only process scalar values');

        new JavaPropertiesWriter()->processConfig(['key' => ['nested']]);
    }

    #[Test]
    public function testNoSection()
    {
        $config = new Config(['test' => 'foo', 'test2.test3' => 'bar']);

        $this->writer->toFile($this->getTestAssetFileName(), $config);

        $config = $this->reader->fromFile($this->getTestAssetFileName());

        static::assertSame('foo', $config['test']);
        static::assertSame('bar', $config['test2.test3']);
    }

    #[Test]
    public function testWriteAndRead()
    {
        static::markTestSkipped('JavaProperties writer cannot handle multi-dimensional configuration');
    }

    #[Test]
    public function testWriteAndReadOriginalFile()
    {
        $config = $this->reader->fromFile(__DIR__ . '/_files/allsections.properties');

        $this->writer->toFile($this->getTestAssetFileName(), $config);

        $config = $this->reader->fromFile($this->getTestAssetFileName());

        static::assertSame('multi', $config['one.two.three']);
    }

    #[Test]
    public function testWriteAndReadOriginalFileWithCustomDelimiter()
    {
        $config = $this->reader->fromFile(__DIR__ . '/_files/allsections.properties');

        $writer = new JavaPropertiesWriter('=');
        $writer->toFile($this->getTestAssetFileName(), $config);

        $reader = new JavaPropertiesReader('=');
        $config = $reader->fromFile($this->getTestAssetFileName());

        static::assertSame('multi', $config['one.two.three']);
    }

    protected function setUp(): void
    {
        $this->reader = new JavaPropertiesReader();
        $this->writer = new JavaPropertiesWriter();
    }
}
