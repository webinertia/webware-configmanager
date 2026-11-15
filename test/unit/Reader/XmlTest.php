<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Reader;

use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader\Xml;
use XMLReader;

use function sys_get_temp_dir;

#[CoversClass(Xml::class)]
final class XmlTest extends AbstractReaderTestCase
{
    #[Test]
    public function closeWhenCallFromFileReaderGetInvalid()
    {
        $configReader = new Xml();

        $configReader->fromFile($this->getTestAssetPath('attributes'));

        $xmlReader = $this->getInternalXmlReader($configReader);

        $this->expectException(Error::class);

        // following operation should fail because the internal reader is closed (and expected to be closed)
        $xmlReader->setParserProperty(
            property: XMLReader::VALIDATE,
            value   : true,
        );
    }

    #[Test]
    public function closeWhenCallFromStringReaderGetInvalid()
    {
        $xml = <<<ECS
            <?xml version="1.0" encoding="UTF-8"?>
            <webware-configmanager>
                <test>foo</test>
                <bar>baz</bar>
                <bar>foo</bar>
            </webware-configmanager>

            ECS;

        $configReader = new Xml();

        $configReader->fromString($xml);

        $xmlReader = $this->getInternalXmlReader($configReader);

        $this->expectException(Error::class);

        // following operation should fail because the internal reader is closed (and expected to be closed)
        $xmlReader->setParserProperty(
            property: XMLReader::VALIDATE,
            value   : true,
        );
    }

    #[Test]
    public function elementWithBothAttributesAndAStringValueIsProcessedCorrectly()
    {
        $this->reader = new Xml();
        $arrayXml     = $this->reader->fromFile($this->getTestAssetPath('attributes'));
        static::assertArrayHasKey('one', $arrayXml);
        static::assertIsArray($arrayXml['one']);

        // No attribute + text value == string
        static::assertArrayHasKey(0, $arrayXml['one']);
        static::assertSame('bazbat', $arrayXml['one'][0]);

        // Attribute(s) + text value == array
        static::assertArrayHasKey(1, $arrayXml['one']);
        static::assertIsArray($arrayXml['one'][1]);
        // Attributes stored in named array keys
        static::assertArrayHasKey('foo', $arrayXml['one'][1]);
        static::assertSame('bar', $arrayXml['one'][1]['foo']);
        // Element value stored in special key '_'
        static::assertArrayHasKey('_', $arrayXml['one'][1]);
        static::assertSame('bazbat', $arrayXml['one'][1]['_']);
    }

    #[Test]
    public function fromString()
    {
        $xml = <<<ECS
            <?xml version="1.0" encoding="UTF-8"?>
            <webware-configmanager>
                <test>foo</test>
                <bar>baz</bar>
                <bar>foo</bar>
            </webware-configmanager>

            ECS;

        $arrayXml = $this->reader->fromString($xml);
        static::assertSame(
            expected: $arrayXml['test'],
            actual  : 'foo',
        );
        static::assertSame(
            expected: $arrayXml['bar'][0],
            actual  : 'baz',
        );
        static::assertSame(
            expected: $arrayXml['bar'][1],
            actual  : 'foo',
        );
    }

    #[Test]
    public function invalidString()
    {
        $xml = <<<ECS
            <?xml version="1.0" encoding="UTF-8"?>
            <webware-configmanager>
                <bar>baz</baz>
            </webware-configmanager>

            ECS;
        $this->expectException(Exception\RuntimeException::class);
        $this->reader->fromString($xml);
    }

    #[Test]
    public function invalidXmlFile()
    {
        $this->reader = new Xml();
        $this->expectException(Exception\RuntimeException::class);
        $this->reader->fromFile($this->getTestAssetPath('invalid'));
    }

    #[Test]
    public function laminas00ArraysWithMultipleChildren()
    {
        $config = $this->reader->fromFile($this->getTestAssetPath('array'));

        static::assertSame('1', $config['six']['seven'][0]['eight']);
        static::assertSame('2', $config['six']['seven'][1]['eight']);
        static::assertSame('3', $config['six']['seven'][2]['eight']);
        static::assertSame('1', $config['six']['seven'][0]['nine']);
        static::assertSame('2', $config['six']['seven'][1]['nine']);
        static::assertSame('3', $config['six']['seven'][2]['nine']);
    }

    #[Test]
    public function laminas00MultipleKeysOfTheSameName()
    {
        $config = $this->reader->fromFile($this->getTestAssetPath('array'));

        static::assertSame('2a', $config['one']['two'][0]);
        static::assertSame('2b', $config['one']['two'][1]);
        static::assertSame('4', $config['three']['four'][1]);
        static::assertSame('5', $config['three']['four'][0]['five']);
    }

    #[Test]
    public function readNonExistingFilesWillFailWithException()
    {
        $configReader = new Xml();

        $this->expectException(Exception\RuntimeException::class);

        $configReader->fromFile(sys_get_temp_dir() . '/path/that/does/not/exist');
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
        return __DIR__ . '/TestAssets/Xml/' . $name . '.xml';
    }

    protected function setUp(): void
    {
        $this->reader = new Xml();
    }

    /**
     * Reads the internal XML reader from a given Xml config reader
     *
     * @return XMLReader
     */
    private function getInternalXmlReader(Xml $xml)
    {
        $reflectionReader = new ReflectionProperty(Xml::class, 'reader');

        $xmlReader = $reflectionReader->getValue($xml);

        static::assertInstanceOf('XMLReader', $xmlReader);

        return $xmlReader;
    }
}
