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
    public function aNullJsonDocumentIsRejectedRatherThanReturningNull()
    {
        // "null" is valid JSON, so json_last_error() reports no error, but the result
        // is not an array. It has to be rejected here instead of escaping as a
        // TypeError from decode()'s return statement.
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid JSON configuration; did not return an array or object');

        $this->reader->fromString('null');
    }

    #[Test]
    public function fromString()
    {
        $json = '{ "test" : "foo", "bar" : [ "baz", "foo" ] }';

        $arrayJson = $this->reader->fromString($json);

        static::assertSame(
            expected: $arrayJson['test'],
            actual  : 'foo',
        );
        static::assertSame(
            expected: $arrayJson['bar'][0],
            actual  : 'baz',
        );
        static::assertSame(
            expected: $arrayJson['bar'][1],
            actual  : 'foo',
        );
    }

    #[Test]
    public function includeAsElement()
    {
        $arrayJson = $this->reader->fromFile($this->getTestAssetPath('include-base_nested'));
        static::assertSame(
            expected: $arrayJson['bar']['foo'],
            actual  : 'foo',
        );
    }

    #[Test]
    public function includeStatementInStringIsRejected()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Cannot process @include statement for a JSON string');

        $this->reader->fromString('{ "@include": "other.json" }');
    }

    #[Test]
    public function invalidJsonFile()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->reader->fromFile($this->getTestAssetPath('invalid'));
    }

    #[Test]
    public function invalidString()
    {
        $json = '{"foo":"bar"';

        $this->expectException(Exception\RuntimeException::class);
        $this->reader->fromString($json);
    }

    #[Test]
    public function jsonThatIsNotAnArrayIsRejected()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid JSON configuration; did not return an array or object');

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
