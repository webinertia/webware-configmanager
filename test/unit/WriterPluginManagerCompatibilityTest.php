<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use Laminas\ServiceManager\ServiceManager;
use Laminas\ServiceManager\Test\CommonPluginManagerTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Exception\InvalidArgumentException;
use Webware\ConfigManager\Writer\AbstractWriter;
use Webware\ConfigManager\WriterPluginManager;

#[CoversClass(WriterPluginManager::class)]
final class WriterPluginManagerCompatibilityTest extends TestCase
{
    use CommonPluginManagerTrait;

    /**
     * @return WriterPluginManager
     */
    protected static function getPluginManager()
    {
        return new WriterPluginManager(new ServiceManager());
    }

    /**
     * The trait declares its data provider with a docblock annotation, which PHPUnit 13 no longer reads.
     */
    #[DataProvider('aliasProvider')]
    #[Test]
    public function testPluginAliasesResolve(string $alias, string $expected): void
    {
        static::assertInstanceOf(
            $expected,
            static::getPluginManager()->get($alias),
            "Alias '{$alias}' does not resolve",
        );
    }

    /**
     * @return string
     */
    protected function getInstanceOf()
    {
        return AbstractWriter::class;
    }

    /**
     * @return string
     */
    protected function getV2InvalidPluginException()
    {
        return InvalidArgumentException::class;
    }
}
