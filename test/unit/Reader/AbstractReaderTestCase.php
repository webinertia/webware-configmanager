<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Reader;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader\ReaderInterface;

abstract class AbstractReaderTestCase extends TestCase
{
    /** @var ReaderInterface */
    protected $reader;

    #[Test]
    public function testFromEmptyString()
    {
        $config = $this->reader->fromString('');
        static::assertEmpty($config);
    }

    #[Test]
    public function testFromFile()
    {
        $config = $this->reader->fromFile($this->getTestAssetPath('include-base'));
        static::assertSame('foo', $config['foo']);
    }

    #[Test]
    public function testMissingFile()
    {
        $filename = $this->getTestAssetPath('no-file');
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage("doesn't exist or not readable");
        $this->reader->fromFile($filename);
    }

    /**
     * Get test asset name for current test case.
     *
     * @param  string $name
     * @return string
     */
    abstract protected function getTestAssetPath($name);
}
