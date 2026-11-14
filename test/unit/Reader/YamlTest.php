<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Reader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader\Yaml as YamlReader;

use function function_exists;
use function is_array;

#[CoversClass(YamlReader::class)]
final class YamlTest extends AbstractReaderTestCase
{
    /**
     * The constructor wires yaml_parse() when ext-yaml is loaded, so clear it to
     * reach the missing-decoder path regardless of the host environment.
     */
    private static function withoutDecoder(): YamlReader
    {
        $reader = new YamlReader();
        new ReflectionProperty(YamlReader::class, 'yamlDecoder')->setValue($reader, null);

        return $reader;
    }

    #[Test]
    public function testConstructorUsesTheYamlExtensionWhenItIsAvailable()
    {
        $reader = new YamlReader();

        if (function_exists('yaml_parse')) {
            static::assertSame('yaml_parse', $reader->getYamlDecoder());
            return;
        }

        static::assertNull($reader->getYamlDecoder());
    }

    #[Test]
    public function testDecoderReturningNullIsReportedAsParseError()
    {
        $reader = new YamlReader(static fn(string $yaml): null => null);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Error parsing YAML data');

        $reader->fromString('foo: bar');
    }

    #[Test]
    public function testFromString()
    {
        $yaml = <<<ECS
            test: foo
            bar:
                - baz
                - foo

            ECS;

        $arrayYaml = $this->reader->fromString($yaml);
        static::assertSame($arrayYaml['test'], 'foo');
        static::assertSame($arrayYaml['bar'][0], 'baz');
        static::assertSame($arrayYaml['bar'][1], 'foo');
    }

    #[Test]
    public function testFromStringWithSection()
    {
        $yaml = <<<ECS
            all:
                test: foo
                bar:
                    - baz
                    - foo

            ECS;

        $arrayYaml = $this->reader->fromString($yaml);
        static::assertSame($arrayYaml['all']['test'], 'foo');
        static::assertSame($arrayYaml['all']['bar'][0], 'baz');
        static::assertSame($arrayYaml['all']['bar'][1], 'foo');
    }

    #[Test]
    public function testIncludeStatementInStringIsRejected()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Cannot process @include statement for a json string');

        $this->reader->fromString("'@include': include-target.yaml");
    }

    #[Test]
    public function testInvalidIniFile()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->reader->fromFile($this->getTestAssetPath('invalid'));
    }

    #[Test]
    public function testReaderWithoutDecoderThrowsOnFromFile()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage("You didn't specify a Yaml callback decoder");

        self::withoutDecoder()->fromFile($this->getTestAssetPath('include-target'));
    }

    #[Test]
    public function testReaderWithoutDecoderThrowsOnFromString()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage("You didn't specify a Yaml callback decoder");

        self::withoutDecoder()->fromString('foo: bar');
    }

    #[Test]
    public function testSetYamlDecoderRejectsNonCallable()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Invalid parameter to setYamlDecoder() - must be callable');

        new YamlReader()->setYamlDecoder('not-a-callable-function-name');
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
        return __DIR__ . '/TestAssets/Yaml/' . $name . '.yaml';
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
    }
}
