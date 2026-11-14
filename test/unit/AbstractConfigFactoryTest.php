<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager;

use ArrayIterator;
use InvalidArgumentException;
use Laminas\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Webware\ConfigManager\AbstractConfigFactory;
use Webware\ConfigManager\Exception as WebwareException;

use function count;

#[CoversClass(AbstractConfigFactory::class)]
final class AbstractConfigFactoryTest extends TestCase
{
    /** @var array */
    protected $config;

    /** @var ServiceManager */
    protected $serviceManager;

    #[Test]
    public function testAddPatternsAcceptsTraversable()
    {
        $factory = new AbstractConfigFactory();
        $factory->addPatterns(new ArrayIterator(['#^custom[\._-](.*)$#']));

        static::assertContains('#^custom[\._-](.*)$#', $factory->getPatterns());
    }

    #[Test]
    public function testAddPatternsRejectsNonTraversable()
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessage('patterns must be array or Traversable');

        new AbstractConfigFactory()->addPatterns('not-a-pattern-list');
    }

    #[Test]
    public function testCanCreateReturnsFalseWhenConfigServiceIsNotAnArray()
    {
        $container = $this->nonArrayConfigContainer();

        static::assertFalse(new AbstractConfigFactory()->canCreate(
            container    : $container,
            requestedName: 'config-mymodule',
        ));
    }

    #[Test]
    public function testCanCreateReturnsFalseWhenConfigurationKeyIsMissing()
    {
        static::assertFalse(new AbstractConfigFactory()->canCreate(
            container    : $this->serviceManager,
            requestedName: 'config-missing',
        ));
    }

    #[Test]
    public function testCanCreateReturnsFalseWhenContainerHasNoConfigService()
    {
        $container = new ServiceManager\ServiceManager();

        static::assertFalse(new AbstractConfigFactory()->canCreate($container, 'MyModule\Config'));
    }

    #[Test]
    public function testCanCreateReturnsFalseWhenNoPatternMatches()
    {
        static::assertFalse(new AbstractConfigFactory()->canCreate(
            container    : $this->serviceManager,
            requestedName: 'NoMatch',
        ));
    }

    /**
     * @return void
     */
    #[Test]
    public function testCanCreateService()
    {
        $factory        = new AbstractConfigFactory();
        $serviceLocator = $this->serviceManager;

        static::assertFalse($factory->canCreate($serviceLocator, 'MyModule\Fail'));
        static::assertTrue($factory->canCreate($serviceLocator, 'MyModule\Config'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testCreateService()
    {
        $serviceLocator = $this->serviceManager;
        static::assertIsArray($serviceLocator->get('MyModule\Config'));
        static::assertIsArray($serviceLocator->get('MyModule_Config'));
        static::assertIsArray($serviceLocator->get('Config.MyModule'));
        static::assertIsArray($serviceLocator->get('phly-blog.config'));
        static::assertIsArray($serviceLocator->get('phly-blog-config'));
        static::assertIsArray($serviceLocator->get('config-phly-blog'));
    }

    #[Test]
    public function testCreateServiceWithRequestedConfigKey()
    {
        $serviceLocator = $this->serviceManager;
        static::assertSame($this->config['MyModule'], $serviceLocator->get('MyModule\Config'));
        static::assertSame($this->config['phly-blog'], $serviceLocator->get('phly-blog-config'));
    }

    #[Test]
    public function testInvalidPattern()
    {
        $factory = new AbstractConfigFactory();

        $this->expectException(InvalidArgumentException::class);
        $factory->addPattern(new stdClass());
    }

    #[Test]
    public function testInvalidPatternIterator()
    {
        $factory = new AbstractConfigFactory();

        $this->expectException(InvalidArgumentException::class);
        $factory->addPatterns('invalid');
    }

    #[Test]
    public function testInvokeReturnsCachedConfigOnSubsequentCalls()
    {
        $factory = new AbstractConfigFactory();
        $factory($this->serviceManager, 'MyModule\Config');
        $result = $factory($this->serviceManager, 'MyModule\Config');

        static::assertSame($this->config['MyModule'], $result);
        static::assertTrue($factory->canCreate($this->serviceManager, 'MyModule\Config'));
    }

    #[Test]
    public function testInvokeThrowsWhenConfigServiceIsNotAnArray()
    {
        $container = $this->nonArrayConfigContainer();

        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Unable to resolve a configuration value for the requested service name "config-mymodule"',
        );

        new AbstractConfigFactory()(
            container    : $container,
            requestedName: 'config-mymodule',
        );
    }

    #[Test]
    public function testInvokeThrowsWhenConfigurationKeyIsMissing()
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Unable to resolve a configuration value for the requested service name "config-missing"',
        );

        new AbstractConfigFactory()(
            container    : $this->serviceManager,
            requestedName: 'config-missing',
        );
    }

    #[Test]
    public function testInvokeThrowsWhenNoPatternMatches()
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Unable to resolve a configuration value for the requested service name "NoMatch"',
        );

        new AbstractConfigFactory()(
            container    : $this->serviceManager,
            requestedName: 'NoMatch',
        );
    }

    /**
     * @return void
     */
    #[Test]
    public function testPatterns()
    {
        $factory  = new AbstractConfigFactory();
        $defaults = $factory->getPatterns();

        // Tests that the accessor returns an array
        static::assertIsArray($defaults);
        static::assertGreaterThan(0, count($defaults));

        // Tests adding a single pattern
        static::assertSame($factory, $factory->addPattern('#foobarone#i'));
        static::assertCount(count($defaults) + 1, $factory->getPatterns());

        // Tests adding multiple patterns at once
        $patterns = $factory->getPatterns();
        static::assertSame($factory, $factory->addPatterns(['#foobartwo#i', '#foobarthree#i']));
        static::assertCount(count($patterns) + 2, $factory->getPatterns());

        // Tests whether the latest added pattern is the first in stack
        $patterns = $factory->getPatterns();
        static::assertSame('#foobarthree#i', $patterns[0]);
    }

    #[Test]
    public function testSetPatternsAcceptsTraversable()
    {
        $factory = new AbstractConfigFactory();
        $factory->setPatterns(new ArrayIterator(['#^custom[\._-](.*)$#']));

        static::assertSame(['#^custom[\._-](.*)$#'], $factory->getPatterns());
    }

    #[Test]
    public function testSetPatternsRejectsNonTraversable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('patterns must be array or Traversable');

        new AbstractConfigFactory()->setPatterns('not-a-pattern-list');
    }

    protected function setUp(): void
    {
        $this->config = [
            'MyModule'  => [
                'foo' => [
                    'bar',
                ],
            ],
            'phly-blog' => [
                'foo' => [
                    'bar',
                ],
            ],
        ];

        $this->serviceManager = new ServiceManager\ServiceManager([
            'abstract_factories' => [
                AbstractConfigFactory::class,
            ],
            'services'           => [
                'config' => $this->config,
            ],
        ]);
    }

    private function nonArrayConfigContainer(): ServiceManager\ServiceManager
    {
        return new ServiceManager\ServiceManager([
            'services' => [
                'config' => 'not-an-array',
            ],
        ]);
    }
}
