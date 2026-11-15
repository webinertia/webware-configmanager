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
use Webware\ConfigManager\Reader;
use Webware\ConfigManager\ReaderPluginManager;

#[CoversClass(ReaderPluginManager::class)]
final class ReaderPluginManagerCompatibilityTest extends TestCase
{
    /**
     * @return array<class-string<Reader\ReaderInterface>, array{class-string<Reader\ReaderInterface>}>
     */
    public static function knownReaderProvider(): array
    {
        return [
            Reader\Ini::class            => [Reader\Ini::class],
            Reader\Json::class           => [Reader\Json::class],
            Reader\Xml::class            => [Reader\Xml::class],
            Reader\Yaml::class           => [Reader\Yaml::class],
            Reader\JavaProperties::class => [Reader\JavaProperties::class],
        ];
    }

    private static function manager(): ReaderPluginManager
    {
        return new ReaderPluginManager(new ServiceManager());
    }

    #[Test]
    public function instanceOfMatchesTheReaderInterface(): void
    {
        $instanceOf = new ReflectionProperty(ReaderPluginManager::class, 'instanceOf');

        static::assertSame(Reader\ReaderInterface::class, $instanceOf->getValue(self::manager()));
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
     * @param class-string<Reader\ReaderInterface> $name
     */
    #[DataProvider('knownReaderProvider')]
    #[Test]
    public function resolvesKnownReaderByClassName(string $name): void
    {
        $manager = self::manager();

        static::assertTrue($manager->has($name));
        static::assertInstanceOf($name, $manager->get($name));
    }
}
