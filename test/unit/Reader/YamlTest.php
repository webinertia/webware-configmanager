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
        new ReflectionProperty(YamlReader::class, 'yamlDecoder')->setValue(
            objectOrValue: $reader,
            value        : null,
        );

        return $reader;
    }

    #[Test]
    public function constructorUsesTheYamlExtensionWhenItIsAvailable()
    {
        $reader = new YamlReader();

        if (function_exists('yaml_parse')) {
            static::assertSame('yaml_parse', $reader->getYamlDecoder());
            return;
        }

        static::assertNull($reader->getYamlDecoder());
    }

    #[Test]
    public function decoderReturningNullIsReportedAsParseError()
    {
        $reader = new YamlReader(static fn(string $yaml): null => null);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Error parsing YAML data');

        $reader->fromString('foo: bar');
    }

    #[Test]
    public function fromString()
    {
        $yaml = <<<ECS
            test: foo
            bar:
                - baz
                - foo

            ECS;

        $arrayYaml = $this->reader->fromString($yaml);
        static::assertSame(
            expected: $arrayYaml['test'],
            actual  : 'foo',
        );
        static::assertSame(
            expected: $arrayYaml['bar'][0],
            actual  : 'baz',
        );
        static::assertSame(
            expected: $arrayYaml['bar'][1],
            actual  : 'foo',
        );
    }

    #[Test]
    public function fromStringWithSection()
    {
        $yaml = <<<ECS
            all:
                test: foo
                bar:
                    - baz
                    - foo

            ECS;

        $arrayYaml = $this->reader->fromString($yaml);
        static::assertSame(
            expected: $arrayYaml['all']['test'],
            actual  : 'foo',
        );
        static::assertSame(
            expected: $arrayYaml['all']['bar'][0],
            actual  : 'baz',
        );
        static::assertSame(
            expected: $arrayYaml['all']['bar'][1],
            actual  : 'foo',
        );
    }

    #[Test]
    public function includeStatementInStringIsRejected()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Cannot process @include statement for a string');

        $this->reader->fromString("'@include': include-target.yaml");
    }

    #[Test]
    public function invalidIniFile()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->reader->fromFile($this->getTestAssetPath('invalid'));
    }

    #[Test]
    public function readerWithoutDecoderThrowsOnFromFile()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs("You didn't specify a Yaml callback decoder");

        self::withoutDecoder()->fromFile($this->getTestAssetPath('include-target'));
    }

    #[Test]
    public function readerWithoutDecoderThrowsOnFromString()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs("You didn't specify a Yaml callback decoder");

        self::withoutDecoder()->fromString('foo: bar');
    }

    #[Test]
    public function setYamlDecoderRejectsNonCallable()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid parameter to setYamlDecoder() - must be callable');

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
