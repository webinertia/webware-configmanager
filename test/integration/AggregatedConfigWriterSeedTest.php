<?php

declare(strict_types=1);

namespace WebwareTestIntegration\ConfigManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Webware\ConfigManager\Reader\Json as JsonReader;
use Webware\ConfigManager\Reader\Yaml as YamlReader;
use Webware\ConfigManager\Writer\Json as JsonWriter;
use Webware\ConfigManager\Writer\PhpArray as PhpArrayWriter;
use Webware\ConfigManager\Writer\WriterInterface;
use Webware\ConfigManager\Writer\Yaml as YamlWriter;
use WebwareTest\ConfigManager\Writer\TestAssets\PhpReader;

use function bin2hex;
use function file_exists;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

/**
 * Seeds the writers with the configuration an application actually aggregates.
 *
 * The writer unit tests use small hand-written arrays, which is the right shape for
 * asserting a format's rules but says nothing about the data a consumer will hand over.
 * What this uses instead is the fixture's real aggregate: class strings as keys, aliases
 * under them, an empty `abstract_factories`, a boolean cache flag, and nested autoloaded
 * config. A format that cannot carry that is a fact worth knowing before a consumer
 * discovers it.
 *
 * Only the formats measured to carry it are here. `Ini` is lossy on it, because an empty
 * array has no representation and `true` reads back as `'1'`. `Xml` cannot use a
 * backslashed class name as an element name. `JavaProperties` refuses arrays by design.
 */
#[CoversClass(JsonWriter::class)]
#[CoversClass(YamlWriter::class)]
#[CoversClass(PhpArrayWriter::class)]
final class AggregatedConfigWriterSeedTest extends TestCase
{
    /**
     * A reader is passed as a callable rather than a ReaderInterface, because the php-array
     * format has no reader in the package and its test asset implements only `fromFile()`.
     *
     * @return iterable<string, array{WriterInterface, callable(string): array<array-key, mixed>, string}>
     */
    public static function formatsThatCarryTheAggregatedConfig(): iterable
    {
        $json = new JsonReader();
        $yaml = new YamlReader(Yaml::parse(...));
        $php  = new PhpReader();

        yield 'json' => [new JsonWriter(), $json->fromFile(...), 'json'];
        yield 'yaml' => [new YamlWriter(Yaml::dump(...)), $yaml->fromFile(...), 'yml'];
        yield 'php array' => [new PhpArrayWriter(), $php->fromFile(...), 'php'];
    }

    #[Test]
    #[DataProvider('formatsThatCarryTheAggregatedConfig')]
    public function theAggregatedConfigSurvivesAWriteAndReadCycle(
        WriterInterface $writer,
        callable $read,
        string $extension,
    ): void {
        $config = require __DIR__ . '/Fixture/application/config/config.php';

        // The aggregate is the interesting input only because it is not tidy: assert the
        // awkward parts are present so a fixture change cannot quietly make this vacuous.
        static::assertArrayHasKey('abstract_factories', $config['dependencies']);
        static::assertTrue($config['config_cache_enabled']);

        $file = sys_get_temp_dir() . "/webware-writer-seed-{$this->suffix}.{$extension}";

        $writer->toFile($file, $config);

        static::assertSame($config, $read($file));
    }

    private string $suffix;

    protected function setUp(): void
    {
        $this->suffix = bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (['json', 'yml', 'php'] as $extension) {
            $file = sys_get_temp_dir() . "/webware-writer-seed-{$this->suffix}.{$extension}";

            if (! file_exists($file)) {
                continue;
            }

            unlink($file);
        }
    }
}
