<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader\Xml as XmlReader;
use Webware\ConfigManager\Writer\Xml as XmlWriter;

use function str_replace;

#[CoversClass(XmlWriter::class)]
final class XmlTest extends AbstractWriterTestCase
{
    #[Test]
    public function aBranchMixingNumericAndStringKeysIsRejected()
    {
        // XML has no way to express a mixed branch, so the writer reports it rather
        // than emitting something the reader cannot round-trip.
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Mixing of string and numeric keys is not allowed');

        $this->writer->toString(['production' => [0 => 'foo', 'name' => 'bar']]);
    }

    #[Test]
    public function addBranchProperyConstructsSubBranchesOfTypeNumeric()
    {
        $config             = new Config([], true);
        $config->production = [['foo'], ['bar']];

        $configString = $this->writer->toString($config);

        $expected = <<<ECS
            <?xml version="1.0" encoding="UTF-8"?>
            <webware-configmanager>
                <production>foo</production>
                <production>bar</production>
            </webware-configmanager>

            ECS;

        static::assertEquals($expected, $configString);
    }

    #[Test]
    public function sectionsToString()
    {
        $config             = new Config([], true);
        $config->production = [];

        $config->production->webhost                    = 'www.example.com';
        $config->production->database                   = [];
        $config->production->database->params           = [];
        $config->production->database->params->host     = 'localhost';
        $config->production->database->params->username = 'production';
        $config->production->database->params->password = 'secret';
        $config->production->database->params->dbname   = 'dbproduction';

        $configString = $this->writer->toString($config);

        $expected = <<<ECS
            <?xml version="1.0" encoding="UTF-8"?>
            <webware-configmanager>
                <production>
                    <webhost>www.example.com</webhost>
                    <database>
                        <params>
                            <host>localhost</host>
                            <username>production</username>
                            <password>secret</password>
                            <dbname>dbproduction</dbname>
                        </params>
                    </database>
                </production>
            </webware-configmanager>

            ECS;

        $expected = str_replace(
            search : "\r\n",
            replace: "\n",
            subject: $expected,
        );
        static::assertEquals($expected, $configString);
    }

    #[Test]
    public function toStringRendersNestedElementsAsXml()
    {
        $config = new Config(['test' => 'foo', 'bar' => [0 => 'baz', 1 => 'foo']]);

        $configString = $this->writer->toString($config);

        $expected = <<<ECS
            <?xml version="1.0" encoding="UTF-8"?>
            <webware-configmanager>
                <test>foo</test>
                <bar>baz</bar>
                <bar>foo</bar>
            </webware-configmanager>

            ECS;

        static::assertEquals($expected, $configString);
    }

    protected function setUp(): void
    {
        $this->writer = new XmlWriter();
        $this->reader = new XmlReader();
    }
}
