<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use InvalidArgumentException;
use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Override;
use Psr\Container\ContainerInterface;
use Traversable;

use function array_key_exists;
use function array_unshift;
use function is_array;
use function is_string;
use function iterator_to_array;
use function preg_match;

class AbstractConfigFactory implements AbstractFactoryInterface
{
    /** @var array */
    protected $configs = [];

    /** @var string[] */
    protected $defaultPatterns = [
        '#config[\._-](.*)$#i',
        '#^(.*)[\\\\\._-]config$#i',
    ];

    /** @var string[] */
    protected $patterns;

    /**
     * @param string $pattern
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function addPattern($pattern)
    {
        if (! is_string($pattern)) {
            throw new Exception\InvalidArgumentException('pattern must be string');
        }

        $patterns = $this->getPatterns();
        array_unshift($patterns, $pattern);
        $this->setPatterns($patterns);
        return $this;
    }

    /**
     * @param array|Traversable $patterns
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function addPatterns($patterns)
    {
        if ($patterns instanceof Traversable) {
            $patterns = iterator_to_array($patterns);
        }

        if (! is_array($patterns)) {
            throw new Exception\InvalidArgumentException('patterns must be array or Traversable');
        }

        foreach ($patterns as $pattern) {
            $this->addPattern($pattern);
        }

        return $this;
    }

    /**
     * Determine if we can create a service (SM v3)
     *
     * @param string $requestedName
     * @return bool
     */
    #[Override]
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        if (array_key_exists($requestedName, $this->configs)) {
            return true;
        }

        if (! $container->has('config')) {
            return false;
        }

        $key = $this->match($requestedName);
        if (null === $key) {
            return false;
        }

        $config = $container->get('config');
        return array_key_exists($key, $config);
    }

    /**
     * Determine if we can create a service with name (SM v2)
     *
     * @param string $name
     * @param string $requestedName
     * @return bool
     */
    #[Override]
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        return $this->canCreate($serviceLocator, $requestedName);
    }

    /**
     * Create service with name (SM v2)
     *
     * @param string $name
     * @param string $requestedName
     * @return string|mixed|array
     */
    #[Override]
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        return $this($serviceLocator, $requestedName);
    }

    /**
     * @return array
     */
    public function getPatterns()
    {
        if (null === $this->patterns) {
            $this->setPatterns($this->defaultPatterns);
        }
        return $this->patterns;
    }

    /**
     * @param array|Traversable $patterns
     * @return self
     * @throws InvalidArgumentException
     */
    public function setPatterns($patterns)
    {
        if ($patterns instanceof Traversable) {
            $patterns = iterator_to_array($patterns);
        }

        if (! is_array($patterns)) {
            throw new InvalidArgumentException('patterns must be array or Traversable');
        }

        $this->patterns = $patterns;
        return $this;
    }

    /**
     * @param string $requestedName
     * @return null|string
     */
    protected function match($requestedName)
    {
        foreach ($this->getPatterns() as $pattern) {
            if (preg_match($pattern, $requestedName, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Create service with name (SM v3)
     *
     * @param string $requestedName
     * @return string|mixed|array
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        if (array_key_exists($requestedName, $this->configs)) {
            return $this->configs[$requestedName];
        }

        $key = $this->match($requestedName);
        if (array_key_exists($key, $this->configs)) {
            $this->configs[$requestedName] = $this->configs[$key];
            return $this->configs[$key];
        }

        $config = $container->get('config');

        $this->configs[$requestedName] = $config[$key];
        $this->configs[$key]           = $config[$key];

        return $config[$key];
    }
}
