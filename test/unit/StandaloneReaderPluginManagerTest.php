<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\Reader;
use Webware\ConfigManager\StandaloneReaderPluginManager;

use function sprintf;

#[CoversClass(StandaloneReaderPluginManager::class)]
final class StandaloneReaderPluginManagerTest extends TestCase
{
    /**
     * @return string[][]
     */
    public static function supportedConfigClassNames()
    {
        return [
            Reader\Ini::class            => [Reader\Ini::class],
            Reader\Json::class           => [Reader\Json::class],
            Reader\Xml::class            => [Reader\Xml::class],
            Reader\Yaml::class           => [Reader\Yaml::class],
            Reader\JavaProperties::class => [Reader\JavaProperties::class],
        ];
    }

    /**
     * @return string[][]
     */
    public static function supportedConfigExtensions()
    {
        return [
            'ini'            => ['ini', Reader\Ini::class],
            'INI'            => ['INI', Reader\Ini::class],
            'json'           => ['json', Reader\Json::class],
            'JSON'           => ['JSON', Reader\Json::class],
            'xml'            => ['xml', Reader\Xml::class],
            'XML'            => ['XML', Reader\Xml::class],
            'yaml'           => ['yaml', Reader\Yaml::class],
            'YAML'           => ['YAML', Reader\Yaml::class],
            'javaproperties' => ['javaproperties', Reader\JavaProperties::class],
            'javaProperties' => ['javaProperties', Reader\JavaProperties::class],
            'JAVAPROPERTIES' => ['JAVAPROPERTIES', Reader\JavaProperties::class],
        ];
    }

    /**
     * @param string $extension Configuration file extension.
     * @param string $expectedType Expected plugin class.
     */
    #[DataProvider('supportedConfigExtensions')]
    #[Test]
    public function testCanRetrieveReaderByExtension($extension, $expectedType)
    {
        $manager = new StandaloneReaderPluginManager();
        static::assertTrue(
            $manager->has($extension),
            sprintf('Failed to assert plugin manager has plugin %s', $extension),
        );

        $plugin = $manager->get($extension);
        static::assertInstanceOf($expectedType, $plugin);
    }

    /**
     * @param string $class Plugin class to retrieve and expect.
     */
    #[DataProvider('supportedConfigClassNames')]
    #[Test]
    public function testCanRetrieveReaderByPluginClassName($class)
    {
        $manager = new StandaloneReaderPluginManager();
        static::assertTrue(
            $manager->has($class),
            sprintf('Failed to assert plugin manager has plugin %s', $class),
        );

        $plugin = $manager->get($class);
        static::assertInstanceOf($class, $plugin);
    }

    #[Test]
    public function testGetThrowsExceptionIfPluginNotFound()
    {
        $manager = new StandaloneReaderPluginManager();
        $this->expectException(Exception\PluginNotFoundException::class);
        $this->expectExceptionMessage('Config reader plugin by name bogus not found');
        $manager->get('bogus');
    }
}
