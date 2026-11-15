<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use Webware\ConfigManager\Writer;
use Webware\ConfigManager\WriterPluginManager;

#[CoversClass(WriterPluginManager::class)]
final class WriterPluginManagerCompatibilityTest extends TestCase
{
    /**
     * @return array<class-string<Writer\AbstractWriter>, array{class-string<Writer\AbstractWriter>}>
     */
    public static function knownWriterProvider(): array
    {
        return [
            Writer\Ini::class            => [Writer\Ini::class],
            Writer\JavaProperties::class => [Writer\JavaProperties::class],
            Writer\Json::class           => [Writer\Json::class],
            Writer\PhpArray::class       => [Writer\PhpArray::class],
            Writer\Xml::class            => [Writer\Xml::class],
            Writer\Yaml::class           => [Writer\Yaml::class],
        ];
    }

    private static function manager(): WriterPluginManager
    {
        return new WriterPluginManager(new ServiceManager());
    }

    #[Test]
    public function instanceOfMatchesTheAbstractWriter(): void
    {
        $instanceOf = new ReflectionProperty(WriterPluginManager::class, 'instanceOf');

        static::assertSame(Writer\AbstractWriter::class, $instanceOf->getValue(self::manager()));
    }

    #[Test]
    public function loadingInvalidElementRaisesException(): void
    {
        $manager = self::manager();
        $manager->setInvokableClass('invalid', stdClass::class);

        $this->expectException(InvalidServiceException::class);
        $manager->get('invalid');
    }

    #[Test]
    public function registeringInvalidElementRaisesException(): void
    {
        $this->expectException(InvalidServiceException::class);

        self::manager()->setService('invalid', $this);
    }

    /**
     * @param class-string<Writer\AbstractWriter> $name
     */
    #[DataProvider('knownWriterProvider')]
    #[Test]
    public function resolvesKnownWriterByClassName(string $name): void
    {
        $manager = self::manager();

        static::assertTrue($manager->has($name));
        static::assertInstanceOf($name, $manager->get($name));
    }
}
