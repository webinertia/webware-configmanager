<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\StandaloneWriterPluginManager;
use Webware\ConfigManager\Writer;

use function sprintf;

#[CoversClass(StandaloneWriterPluginManager::class)]
final class StandaloneWriterPluginManagerTest extends TestCase
{
    /**
     * @return string[][]
     */
    public static function supportedConfigClassNames()
    {
        return [
            Writer\Ini::class      => [Writer\Ini::class],
            Writer\Json::class     => [Writer\Json::class],
            Writer\PhpArray::class => [Writer\PhpArray::class],
            Writer\Xml::class      => [Writer\Xml::class],
            Writer\Yaml::class     => [Writer\Yaml::class],
        ];
    }

    /**
     * @return string[][]
     */
    public static function supportedConfigTypes()
    {
        return [
            'ini'      => ['ini', Writer\Ini::class],
            'INI'      => ['INI', Writer\Ini::class],
            'json'     => ['json', Writer\Json::class],
            'JSON'     => ['JSON', Writer\Json::class],
            'php'      => ['php', Writer\PhpArray::class],
            'PHP'      => ['PHP', Writer\PhpArray::class],
            'phparray' => ['phparray', Writer\PhpArray::class],
            'phpArray' => ['phpArray', Writer\PhpArray::class],
            'PhpArray' => ['PhpArray', Writer\PhpArray::class],
            'xml'      => ['xml', Writer\Xml::class],
            'XML'      => ['XML', Writer\Xml::class],
            'yaml'     => ['yaml', Writer\Yaml::class],
            'YAML'     => ['YAML', Writer\Yaml::class],
        ];
    }

    /**
     * @param string $class Plugin class to retrieve and expect.
     */
    #[DataProvider('supportedConfigClassNames')]
    #[Test]
    public function testCanRetrieveWriterByPluginClassName($class)
    {
        $manager = new StandaloneWriterPluginManager();
        static::assertTrue(
            $manager->has($class),
            sprintf('Failed to assert plugin manager has plugin %s', $class),
        );

        $plugin = $manager->get($class);
        static::assertInstanceOf($class, $plugin);
    }

    /**
     * @param string $type Configuration type.
     * @param string $expectedType Expected plugin class.
     */
    #[DataProvider('supportedConfigTypes')]
    #[Test]
    public function testCanRetrieveWriterByType($type, $expectedType)
    {
        $manager = new StandaloneWriterPluginManager();
        static::assertTrue(
            $manager->has($type),
            sprintf('Failed to assert plugin manager has plugin %s', $type),
        );

        $plugin = $manager->get($type);
        static::assertInstanceOf($expectedType, $plugin);
    }

    #[Test]
    public function testGetThrowsExceptionIfPluginNotFound()
    {
        $manager = new StandaloneWriterPluginManager();
        $this->expectException(Exception\PluginNotFoundException::class);
        $this->expectExceptionMessage('Config writer plugin by name bogus not found');
        $manager->get('bogus');
    }
}
