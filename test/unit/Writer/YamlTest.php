<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader\Yaml as YamlReader;
use Webware\ConfigManager\Writer\Yaml as YamlWriter;

use function function_exists;
use function is_array;

#[CoversClass(YamlWriter::class)]
final class YamlTest extends AbstractWriterTestCase
{
    #[Test]
    public function constructorUsesTheYamlExtensionWhenItIsAvailable()
    {
        $writer = new YamlWriter();

        if (function_exists('yaml_emit')) {
            static::assertSame('yaml_emit', $writer->getYamlEncoder());
            return;
        }

        static::assertNull($writer->getYamlEncoder());
    }

    #[Test]
    public function encoderReturningNullIsReportedAsGenerationError()
    {
        $writer = new YamlWriter(static function (array $config) {
            return null;
        });

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Error generating YAML data');

        $writer->toString(['test' => 'foo']);
    }

    #[Test]
    public function getYamlEncoderReturnsConfiguredCallback()
    {
        $encoder = static fn(array $config): string => Yaml::dump($config);

        static::assertSame($encoder, new YamlWriter($encoder)->getYamlEncoder());
    }

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
    public function setYamlEncoderRejectsNonCallable()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid parameter to setYamlEncoder() - must be callable');

        new YamlWriter()->setYamlEncoder('not-a-callable-function-name');
    }

    #[Test]
    public function writeAndReadOriginalFile()
    {
        $config = $this->reader->fromFile(__DIR__ . '/_files/allsections.yaml');

        $this->writer->toFile($this->getTestAssetFileName(), $config);

        $config = $this->reader->fromFile($this->getTestAssetFileName());

        static::assertSame('multi', $config['all']['one']['two']['three']);
    }

    #[Test]
    public function writerWithoutEncoderThrows()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs("You didn't specify a Yaml callback encoder");

        $writer = new YamlWriter();
        new ReflectionProperty(YamlWriter::class, 'yamlEncoder')->setValue(
            objectOrValue: $writer,
            value        : null,
        );

        $writer->toString(['test' => 'foo']);
    }

    protected function setUp(): void
    {
        $this->reader = new YamlReader(
            static function (string $yaml): ?array {
                try {
                    $parsed = Yaml::parse($yaml);
                } catch (ParseException) {
                    return null;
                }

                return is_array($parsed) ? $parsed : null;
            },
        );
        $this->writer = new YamlWriter(Yaml::dump(...));
    }
}
