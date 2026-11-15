<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception as WebwareException;
use Webware\ConfigManager\Reader\Ini as IniReader;
use Webware\ConfigManager\Writer\Ini as IniWriter;

#[CoversClass(IniWriter::class)]
final class IniTest extends AbstractWriterTestCase
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
    public function numericAndBooleanValuesAreRenderedUnquoted()
    {
        $writer = new IniWriter();

        $result = $writer->toString(['count' => 3, 'enabled' => true]);

        static::assertStringContainsString('count = 3', $result);
        static::assertStringContainsString('enabled = true', $result);
    }

    #[Test]
    public function renderWithoutSectionsFlattensNestedKeys()
    {
        $writer = new IniWriter();
        $writer->setRenderWithoutSectionsFlags(true);
        $writer->setNestSeparator('.');

        static::assertTrue($writer->renderWithoutSections);
        static::assertSame('.', $writer->nestSeparator);

        $result = $writer->toString(['group' => ['key' => 'value']]);

        static::assertSame("group.key = \"value\"\n", $result);
    }

    #[Test]
    public function toStringRendersScalarAndSectionEntries()
    {
        $writer = new IniWriter();

        $result = $writer->toString([
            'top'     => 'scalar',
            'section' => ['key' => 'value'],
        ]);

        static::assertStringContainsString('top = "scalar"', $result);
        static::assertStringContainsString("[section]\nkey = \"value\"", $result);
    }

    #[Test]
    public function valueContainingDoubleQuotesIsRejected()
    {
        $this->expectException(WebwareException\RuntimeException::class);
        $this->expectExceptionMessageIs('Value can not contain double quotes');

        new IniWriter()->toString(['key' => 'va"lue']);
    }

    #[Test]
    public function writeAndReadOriginalFile()
    {
        $config = $this->reader->fromFile(__DIR__ . '/_files/allsections.ini');

        $this->writer->toFile($this->getTestAssetFileName(), $config);

        $config = $this->reader->fromFile($this->getTestAssetFileName());

        static::assertSame('multi', $config['all']['one']['two']['three']);
    }

    protected function setUp(): void
    {
        $this->reader = new IniReader();
        $this->writer = new IniWriter();
    }
}
