<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Container;

use ArrayIterator;
use InvalidArgumentException;
use Laminas\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psl\Type;
use stdClass;
use Webware\ConfigManager\Container\AbstractConfigFactory;
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
    public function addPatternsAcceptsTraversable()
    {
        $factory = new AbstractConfigFactory();
        $factory->addPatterns(new ArrayIterator(['#^custom[\._-](.*)$#']));

        static::assertContains('#^custom[\._-](.*)$#', $factory->getPatterns());
    }

    #[Test]
    public function addPatternsRejectsNonTraversable()
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('patterns must be array or Traversable');

        new AbstractConfigFactory()->addPatterns('not-a-pattern-list');
    }

    #[Test]
    public function anEmptyPatternIsRejected(): void
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Each pattern must be a non-empty string; received "string"');

        new AbstractConfigFactory()->setPatterns(['']);
    }

    #[Test]
    public function aPatternThatIsNotANonEmptyStringIsRejected(): void
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Each pattern must be a non-empty string; received "int"');

        new AbstractConfigFactory()->setPatterns(['#^a(.*)$#', 12_345]);
    }

    #[Test]
    public function aPatternThatIsNotARegularExpressionIsRejected(): void
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Pattern "not-a-regex" is not a valid regular expression');

        new AbstractConfigFactory()->addPattern('not-a-regex');
    }

    #[Test]
    public function aPatternWithoutACapturingGroupIsTreatedAsNoMatchRatherThanWarning(): void
    {
        $factory = new AbstractConfigFactory();
        $factory->setPatterns(['#^config-mymodule$#']);

        // preg_match() matches here but reports no group at index 1. Treating that as a
        // non-match keeps it from surfacing as an undefined-array-key warning.
        static::assertFalse($factory->canCreate(
            container    : $this->serviceManager,
            requestedName: 'config-mymodule',
        ));
    }

    #[Test]
    public function canCreateReturnsFalseWhenConfigServiceIsNotAnArray()
    {
        $container = $this->nonArrayConfigContainer();

        static::assertFalse(new AbstractConfigFactory()->canCreate(
            container    : $container,
            requestedName: 'config-mymodule',
        ));
    }

    #[Test]
    public function canCreateReturnsFalseWhenConfigurationKeyIsMissing()
    {
        static::assertFalse(new AbstractConfigFactory()->canCreate(
            container    : $this->serviceManager,
            requestedName: 'config-missing',
        ));
    }

    #[Test]
    public function canCreateReturnsFalseWhenConfigurationValueIsNull()
    {
        // A null entry is an absent entry: upstream used isset(), which treats null as
        // not set, and that keeps a null from being handed out as a resolved service.
        static::assertFalse(new AbstractConfigFactory()->canCreate(
            container    : $this->nullValueConfigContainer(),
            requestedName: 'config-mymodule',
        ));
    }

    #[Test]
    public function canCreateReturnsFalseWhenContainerHasNoConfigService()
    {
        $container = new ServiceManager\ServiceManager();

        static::assertFalse(new AbstractConfigFactory()->canCreate(
            container    : $container,
            requestedName: 'MyModule\Config',
        ));
    }

    #[Test]
    public function canCreateReturnsFalseWhenNoPatternMatches()
    {
        static::assertFalse(new AbstractConfigFactory()->canCreate(
            container    : $this->serviceManager,
            requestedName: 'NoMatch',
        ));
    }

    #[Test]
    public function canCreateReturnsTrueForFalsyButNonNullValues()
    {
        $factory = new AbstractConfigFactory();

        foreach (['empty', 'zero', 'false', 'empty-array'] as $key) {
            static::assertTrue(
                $factory->canCreate(
                    container    : $this->nullValueConfigContainer(),
                    requestedName: "config-{$key}",
                ),
                "{$key} should resolve: only null is treated as unset",
            );
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function canCreateService()
    {
        $factory        = new AbstractConfigFactory();
        $serviceLocator = $this->serviceManager;

        static::assertFalse($factory->canCreate(
            container    : $serviceLocator,
            requestedName: 'MyModule\Fail',
        ));
        static::assertTrue($factory->canCreate(
            container    : $serviceLocator,
            requestedName: 'MyModule\Config',
        ));
    }

    /**
     * @return void
     */
    #[Test]
    public function createService()
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
    public function createServiceWithRequestedConfigKey()
    {
        $serviceLocator = $this->serviceManager;
        static::assertSame($this->config['MyModule'], $serviceLocator->get('MyModule\Config'));
        static::assertSame($this->config['phly-blog'], $serviceLocator->get('phly-blog-config'));
    }

    #[Test]
    public function invalidPattern()
    {
        $factory = new AbstractConfigFactory();

        $this->expectException(InvalidArgumentException::class);
        $factory->addPattern(new stdClass());
    }

    #[Test]
    public function invalidPatternIterator()
    {
        $factory = new AbstractConfigFactory();

        $this->expectException(InvalidArgumentException::class);
        $factory->addPatterns('invalid');
    }

    #[Test]
    public function invokeReturnsCachedConfigOnSubsequentCalls()
    {
        $factory = new AbstractConfigFactory();
        $factory(
            container    : $this->serviceManager,
            requestedName: 'MyModule\Config',
        );
        $result = $factory(
            container    : $this->serviceManager,
            requestedName: 'MyModule\Config',
        );

        static::assertSame($this->config['MyModule'], $result);
        static::assertTrue($factory->canCreate(
            container    : $this->serviceManager,
            requestedName: 'MyModule\Config',
        ));
    }

    #[Test]
    public function invokeThrowsWhenConfigServiceIsNotAnArray()
    {
        $container = $this->nonArrayConfigContainer();

        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'Unable to resolve a configuration value for the requested service name "config-mymodule"',
        );

        new AbstractConfigFactory()(
            container    : $container,
            requestedName: 'config-mymodule',
        );
    }

    #[Test]
    public function invokeThrowsWhenConfigurationKeyIsMissing()
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'Unable to resolve a configuration value for the requested service name "config-missing"',
        );

        new AbstractConfigFactory()(
            container    : $this->serviceManager,
            requestedName: 'config-missing',
        );
    }

    #[Test]
    public function invokeThrowsWhenConfigurationValueIsNull()
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'Unable to resolve a configuration value for the requested service name "config-mymodule"',
        );

        new AbstractConfigFactory()(
            container    : $this->nullValueConfigContainer(),
            requestedName: 'config-mymodule',
        );
    }

    #[Test]
    public function invokeThrowsWhenNoPatternMatches()
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
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
    public function patterns()
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
    public function resolveDoesNotPoisonTheUntypedCache(): void
    {
        // __invoke() memoizes the raw entry. A typed resolve() must not write a shaped
        // value back, or a later untyped call would receive the coerced form instead.
        $factory = new AbstractConfigFactory();
        $type    = Type\shape(['foo' => Type\vec(Type\string())]);

        $factory->resolve(
            container    : $this->serviceManager,
            requestedName: 'config-MyModule',
            type         : $type,
        );

        static::assertSame(
            $this->config['MyModule'],
            $factory(
                container    : $this->serviceManager,
                requestedName: 'config-MyModule',
            ),
        );
    }

    #[Test]
    public function resolveRejectsAnEntryItCannotFind(): void
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'Unable to resolve a configuration value for the requested service name "NoMatch"',
        );

        new AbstractConfigFactory()->resolve(
            container    : $this->serviceManager,
            requestedName: 'NoMatch',
            type         : Type\mixed(),
        );
    }

    #[Test]
    public function resolveRejectsAnEntryThatDoesNotMatchTheType(): void
    {
        $this->expectException(WebwareException\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Configuration entry "MyModule" does not match the expected type');

        new AbstractConfigFactory()->resolve(
            container    : $this->serviceManager,
            requestedName: 'config-MyModule',
            type         : Type\shape(['foo' => Type\string()]),
        );
    }

    #[Test]
    public function resolveShapesTheEntryWithACallerSuppliedType(): void
    {
        $type = Type\shape([
            'foo' => Type\vec(Type\string()),
        ]);

        static::assertSame(
            ['foo' => ['bar']],
            new AbstractConfigFactory()->resolve(
                container    : $this->serviceManager,
                requestedName: 'config-MyModule',
                type         : $type,
            ),
        );
    }

    #[Test]
    public function setPatternsAcceptsTraversable()
    {
        $factory = new AbstractConfigFactory();
        $factory->setPatterns(new ArrayIterator(['#^custom[\._-](.*)$#']));

        static::assertSame(['#^custom[\._-](.*)$#'], $factory->getPatterns());
    }

    #[Test]
    public function setPatternsRejectsNonTraversable()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('patterns must be array or Traversable');

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

    /**
     * A container holding a null entry alongside values that are falsy but not null,
     * so the tests below separate "unset" from "falsy" rather than conflating them.
     */
    private function nullValueConfigContainer(): ServiceManager\ServiceManager
    {
        return new ServiceManager\ServiceManager([
            'services' => [
                'config' => [
                    'mymodule'    => null,
                    'empty'       => '',
                    'zero'        => 0,
                    'false'       => false,
                    'empty-array' => [],
                ],
            ],
        ]);
    }
}
