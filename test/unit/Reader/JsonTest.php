<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Reader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader\Json;

#[CoversClass(Json::class)]
final class JsonTest extends AbstractReaderTestCase
{
    #[Test]
    public function testFromString()
    {
        $json = '{ "test" : "foo", "bar" : [ "baz", "foo" ] }';

        $arrayJson = $this->reader->fromString($json);

        static::assertSame($arrayJson['test'], 'foo');
        static::assertSame($arrayJson['bar'][0], 'baz');
        static::assertSame($arrayJson['bar'][1], 'foo');
    }

    #[Test]
    public function testIncludeAsElement()
    {
        $arrayJson = $this->reader->fromFile($this->getTestAssetPath('include-base_nested'));
        static::assertSame($arrayJson['bar']['foo'], 'foo');
    }

    #[Test]
    public function testIncludeStatementInStringIsRejected()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Cannot process @include statement for a JSON string');

        $this->reader->fromString('{ "@include": "other.json" }');
    }

    #[Test]
    public function testInvalidJsonFile()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->reader->fromFile($this->getTestAssetPath('invalid'));
    }

    #[Test]
    public function testInvalidString()
    {
        $json = '{"foo":"bar"';

        $this->expectException(Exception\RuntimeException::class);
        $this->reader->fromString($json);
    }

    #[Test]
    public function testJsonThatIsNotAnArrayIsRejected()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON configuration; did not return an array or object');

        $this->reader->fromString('"a string"');
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
        return __DIR__ . '/TestAssets/Json/' . $name . '.json';
    }

    protected function setUp(): void
    {
        $this->reader = new Json();
    }
}
