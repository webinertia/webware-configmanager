<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader\Json as JsonReader;
use Webware\ConfigManager\Writer\Json as JsonWriter;

#[CoversClass(JsonWriter::class)]
final class JsonTest extends AbstractWriterTestCase
{
    #[Test]
    public function noSection()
    {
        $config = new Config(['test' => 'foo', 'test2' => ['test3' => 'bar']]);

        $this->writer->toFile($this->getTestAssetFileName(), $config);

        $config = $this->reader->fromFile($this->getTestAssetFileName());

        static::assertSame('foo', $config['test']);
        static::assertSame('bar', $config['test2']['test3']);
    }

    #[Test]
    public function unencodableDataRaisesException()
    {
        $this->expectException(Exception\RuntimeException::class);

        new JsonWriter()->toString(['key' => "\xB1\x31"]);
    }

    #[Test]
    public function writeAndReadOriginalFile()
    {
        $config = $this->reader->fromFile(__DIR__ . '/_files/allsections.json');

        $this->writer->toFile($this->getTestAssetFileName(), $config);

        $config = $this->reader->fromFile($this->getTestAssetFileName());

        static::assertSame('multi', $config['all']['one']['two']['three']);
    }

    protected function setUp(): void
    {
        $this->reader = new JsonReader();
        $this->writer = new JsonWriter();
    }
}
