<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Provider\ConfigProviderResolver;
use Webware\ConfigManager\Provider\PhpFileProvider;
use Webware\ConfigManager\Shape;

use function iterator_to_array;
use function realpath;
use function sprintf;

use const DIRECTORY_SEPARATOR;

#[CoversClass(PhpFileProvider::class)]
#[CoversClass(Shape::class)]
final class PhpFileProviderTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/TestAssets/Php';

    #[Test]
    public function aFileThatDoesNotReturnAnArrayIsRejectedWithItsPath(): void
    {
        $provider = $this->provider('scalar.php');
        $path     = $this->path('scalar.php');

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            sprintf('Config file "%s" must return an array; it returned "string"', $path),
        );

        iterator_to_array($provider());
    }

    #[Test]
    public function anEmptyPatternIsRejected(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('A config file pattern must not be empty');

        new PhpFileProvider('');
    }

    #[Test]
    public function eachMatchingFileIsYieldedAsItsOwnArray(): void
    {
        static::assertSame(
            [
                ['layers' => ['global']],
                ['layers' => ['first-global'], 'first' => true],
                ['layers' => ['second-global']],
                ['layers' => ['first-local']],
            ],
            $this->contributions('{{,*.}global,{,*.}local}.php'),
        );
    }

    #[Test]
    public function theStreamIsMergedInFileOrderByTheResolver(): void
    {
        // End to end through the resolver, which consumes the Generator. Integer keys
        // append, so the layer names accumulate in file order.
        $config = new ConfigProviderResolver()->aggregate([
            new PhpFileProvider($this->path('{{,*.}global,{,*.}local}.php')),
        ]);

        static::assertSame(
            ['layers' => ['global', 'first-global', 'second-global', 'first-local'], 'first' => true],
            $config,
        );
    }

    /** @return list<array<array-key, mixed>> */
    private function contributions(string $pattern): array
    {
        return iterator_to_array($this->provider($pattern)(), preserve_keys: false);
    }

    private function path(string $pattern): string
    {
        $directory = realpath(self::FIXTURES);

        static::assertIsString($directory);

        return $directory . DIRECTORY_SEPARATOR . $pattern;
    }

    private function provider(string $pattern): PhpFileProvider
    {
        return new PhpFileProvider($this->path($pattern));
    }
}
