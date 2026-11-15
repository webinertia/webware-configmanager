<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Writer\PhpArray;
use WebwareTest\ConfigManager\Writer\TestAssets\DummyClassA;
use WebwareTest\ConfigManager\Writer\TestAssets\DummyClassB;
use WebwareTest\ConfigManager\Writer\TestAssets\PhpReader;

use function class_exists;
use function file_get_contents;
use function file_put_contents;
use function preg_replace;
use function sprintf;
use function trim;

use const PHP_EOL;

#[CoversClass(PhpArray::class)]
final class PhpArrayTest extends AbstractWriterTestCase
{
    #[Test]
    public function bracketArraySyntaxIsUsedByDefault()
    {
        static::assertTrue($this->writer->useBracketArraySyntax);
    }

    #[Test]
    public function classNameScalarsAreUsedByDefault()
    {
        static::assertTrue($this->writer->useClassNameScalars);
    }

    #[Test]
    public function keysAreAlignedWithinEachScope()
    {
        // Each array literal is padded to its own widest key, which is how mago fmt
        // aligns them, and numeric keys take part in the width.
        $configString = $this->writer->toString(new Config([
            'a'         => 1,
            'longerKey' => 2,
            'mid'       => 3,
            'nested'    => ['x' => 'y', 'veryLongInnerKey' => 'z', 'q' => 1],
            0           => 'zero',
            10          => 'ten',
            'mixed'     => [0 => 'a', 100 => 'b'],
        ]));

        $expected = "<?php\n\n";
        $expected .= "return [\n";
        $expected .= "    'a'         => 1,\n";
        $expected .= "    'longerKey' => 2,\n";
        $expected .= "    'mid'       => 3,\n";
        $expected .= "    'nested'    => [\n";
        $expected .= "        'x'                => 'y',\n";
        $expected .= "        'veryLongInnerKey' => 'z',\n";
        $expected .= "        'q'                => 1,\n";
        $expected .= "    ],\n";
        $expected .= "    0           => 'zero',\n";
        $expected .= "    10          => 'ten',\n";
        $expected .= "    'mixed'     => [\n";
        $expected .= "        0   => 'a',\n";
        $expected .= "        100 => 'b',\n";
        $expected .= "    ],\n";
        $expected .= "];\n";

        static::assertSame($expected, $configString);
    }

    #[Test]
    public function render()
    {
        $config = new Config([
            'test'       => 'foo',
            'bar'        => [0 => 'baz', 1 => 'foo'],
            'emptyArray' => [],
            'object'     => (object) ['foo' => 'bar'],
            'integer'    => 123,
            'boolean'    => false,
            'null'       => null,
        ]);

        $configString = $this->writer->toString($config);

        // build string line by line as we are trailing-whitespace sensitive.
        $expected = "<?php\n\n";
        $expected .= "return [\n";
        $expected .= "    'test'       => 'foo',\n";
        $expected .= "    'bar'        => [\n";
        $expected .= "        0 => 'baz',\n";
        $expected .= "        1 => 'foo',\n";
        $expected .= "    ],\n";
        $expected .= "    'emptyArray' => [],\n";
        $expected .= "    'object'     => (object) array(\n";
        $expected .= "   'foo' => 'bar',\n";
        $expected .= "),\n";
        $expected .= "    'integer'    => 123,\n";
        $expected .= "    'boolean'    => false,\n";
        $expected .= "    'null'       => null,\n";
        $expected .= "];\n";

        static::assertSame($expected, $configString);
    }

    #[Test]
    public function renderConvertsNestedTraversables()
    {
        $configString = $this->writer->toString([
            'default' => new ArrayIterator(['test' => new ArrayIterator(['deep' => 'foo'])]),
        ]);

        $expected = "<?php\n\n";
        $expected .= "return [\n";
        $expected .= "    'default' => [\n";
        $expected .= "        'test' => [\n";
        $expected .= "            'deep' => 'foo',\n";
        $expected .= "        ],\n";
        $expected .= "    ],\n";
        $expected .= "];\n";

        static::assertSame($expected, $configString);
    }

    #[Test]
    public function renderEscapesKeysThatAreNotClassNames()
    {
        $configString = $this->writer->toString(new Config([
            '\\~'                   => 'bar',
            ''                      => 'emptyString',
            'TestAssets\DummyClass' => 'foo',
        ]));

        $expected = <<<'ECS'
            <?php

            return [
                '\\~'                    => 'bar',
                ''                       => 'emptyString',
                'TestAssets\\DummyClass' => 'foo',
            ];

            ECS;

        static::assertSame($expected, $configString);
    }

    #[Test]
    public function renderWithClassNameScalars()
    {
        $dummyFqnA = DummyClassA::class;
        $dummyFqnB = DummyClassB::class;

        // Dummy classes should not be loaded prior this test
        $message = sprintf('class %s should not be loaded prior test', $dummyFqnA);
        static::assertFalse(class_exists($dummyFqnA, false), $message);

        $message = sprintf('class %s should not be loaded prior test', $dummyFqnB);
        static::assertFalse(class_exists($dummyFqnB, false), $message);

        $config = new Config([
            $dummyFqnA       => ['fqnValue' => $dummyFqnB],
            "\\{$dummyFqnA}" => '',
        ]);

        $expected = <<<ECS
            <?php

            return [
                \\{$dummyFqnA}::class => [
                    'fqnValue' => \\{$dummyFqnB}::class,
                ],
                \\{$dummyFqnA}::class => '',
            ];

            ECS;

        static::assertSame($expected, $this->writer->toString($config));
    }

    #[Test]
    public function renderWithLegacyArraySyntax()
    {
        $config = new Config(['test' => 'foo', 'bar' => [0 => 'baz', 1 => 'foo'], 'emptyArray' => []]);

        $this->writer->setUseBracketArraySyntax(false);
        $configString = $this->writer->toString($config);

        // build string line by line as we are trailing-whitespace sensitive.
        $expected = "<?php\n\n";
        $expected .= "return array(\n";
        $expected .= "    'test'       => 'foo',\n";
        $expected .= "    'bar'        => array(\n";
        $expected .= "        0 => 'baz',\n";
        $expected .= "        1 => 'foo',\n";
        $expected .= "    ),\n";
        $expected .= "    'emptyArray' => array(),\n";
        $expected .= ");\n";

        static::assertSame($expected, $configString);
    }

    #[Test]
    public function renderWithQuotesInString()
    {
        $config = new Config(['one' => 'Test with "double" quotes', 'two' => 'Test with \'single\' quotes']);

        $configString = $this->writer->toString($config);

        $expected = "<?php\n\n";
        $expected .= "return [\n";
        $expected .= "    'one' => 'Test with \"double\" quotes',\n";
        $expected .= "    'two' => 'Test with \\'single\\' quotes',\n";
        $expected .= "];\n";

        static::assertSame($expected, $configString);
    }

    #[Test]
    public function setUseBracketArraySyntaxReturnsFluentInterface()
    {
        static::assertSame($this->writer, $this->writer->setUseBracketArraySyntax(true));
    }

    #[Test]
    public function setUseClassNameScalarsReturnsFluentInterface()
    {
        static::assertSame($this->writer, $this->writer->setUseClassNameScalars(true));
    }

    #[Test]
    public function writeAndReadANestedTraversableValue()
    {
        $filename = $this->getTestAssetFileName();

        $this->writer->toFile($filename, new Config(['default' => new ArrayIterator(['test' => 'foo'])]));

        $read = $this->reader->fromFile($filename);

        static::assertSame('foo', $read['default']['test']);
    }

    #[Test]
    public function writeConvertsPathToDirWhenWritingBackToFile()
    {
        $filename = $this->getTestAssetFileName();
        file_put_contents($filename, file_get_contents(__DIR__ . '/_files/array.php'));

        $this->writer->toFile($filename, include $filename);

        // Ensure file endings are same
        $expected = trim(file_get_contents(__DIR__ . '/_files/array.php'));
        $expected = preg_replace("~\r\n|\n|\r~", PHP_EOL, $expected);

        $result = trim(file_get_contents($filename));
        $result = preg_replace("~\r\n|\n|\r~", PHP_EOL, $result);

        static::assertSame($expected, $result);
    }

    protected function setUp(): void
    {
        $this->writer = new PhpArray();
        $this->reader = new PhpReader();
    }
}
