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
    public function aDirectoryIsRejectedRatherThanRead(): void
    {
        // A directory exists and is readable, so only the is_file() half of the guard
        // rejects it. The message names the guard, which is what distinguishes this from
        // whatever the read would otherwise fail with.
        $directory = __DIR__ . '/TestAssets/Json';

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessageIs("File '{$directory}' doesn't exist or not readable");

        $this->reader->fromFile($directory);
    }

    #[Test]
    public function anIncludeIsMergedRecursively(): void
    {
        // The document brings its own nested data and the include brings more. Both must
        // survive; replacing the array wholesale would drop the document's own key.
        $config = $this->reader->fromFile($this->getTestAssetPath('include-recursive-base'));

        static::assertSame(['a' => 1, 'b' => 2], $config['config']);
    }

    #[Test]
    public function anIncludeIsRecognisedWhenItsKeyIsPadded(): void
    {
        // The directive is matched after trimming, so " @include " is one too. Without the
        // trim it stays a literal key and the file it names is never read.
        $config = $this->reader->fromFile($this->getTestAssetPath('include-padded'));

        static::assertArrayNotHasKey('  @include  ', $config);
        static::assertArrayHasKey('foo', $config);
    }

    #[Test]
    public function anIncludeIsResolvedAgainstTheDocumentThatDeclaredIt(): void
    {
        // The second include must resolve against this file's directory. Reading the first
        // one reassigns the directory, so unless it is read through a copy, the second
        // resolves under sub/ and the file is not found.
        $config = $this->reader->fromFile($this->getTestAssetPath('include-scoping'));

        static::assertSame(['inner' => 'value'], $config['first']);
        static::assertSame(['scoped' => 'value'], $config['second']);
    }

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
        $this->expectExceptionMessageIs('Cannot process @include statement for a string');

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
