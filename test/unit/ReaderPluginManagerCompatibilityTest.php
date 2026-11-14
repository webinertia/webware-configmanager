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
use Webware\ConfigManager\Reader\ReaderInterface;
use Webware\ConfigManager\ReaderPluginManager;

#[CoversClass(ReaderPluginManager::class)]
final class ReaderPluginManagerCompatibilityTest extends TestCase
{
    use CommonPluginManagerTrait;

    /**
     * @return ReaderPluginManager
     */
    protected static function getPluginManager()
    {
        return new ReaderPluginManager(new ServiceManager());
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
        return ReaderInterface::class;
    }

    /**
     * @return string
     */
    protected function getV2InvalidPluginException()
    {
        return InvalidArgumentException::class;
    }
}
