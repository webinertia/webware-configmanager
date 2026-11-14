<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use InvalidArgumentException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Traversable;

use function array_key_exists;
use function is_array;
use function is_string;
use function iterator_to_array;
use function preg_match;
use function sprintf;

class AbstractConfigFactory implements AbstractFactoryInterface
{
    /** @var array<string, mixed> */
    protected array $configs = [];

    /** @var list<string> */
    protected array $defaultPatterns = [
        '#config[\._-](.*)$#i',
        '#^(.*)[\\\\\._-]config$#i',
    ];

    /** @var array<array-key, string>|null */
    protected ?array $patterns = null;

    /**
     * @throws Exception\InvalidArgumentException
     */
    public function addPattern(mixed $pattern): self
    {
        if (! is_string($pattern)) {
            throw new Exception\InvalidArgumentException('pattern must be string');
        }

        return $this->setPatterns([$pattern, ...$this->getPatterns()]);
    }

    /**
     * @throws Exception\InvalidArgumentException
     */
    public function addPatterns(mixed $patterns): self
    {
        foreach ($this->assertPatternList($patterns) as $pattern) {
            $this->addPattern($pattern);
        }

        return $this;
    }

    /**
     * Determine if the requested service name maps to a configuration key.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Override]
    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        return null !== $this->find($container, $requestedName);
    }

    /**
     * @return array<array-key, string>
     */
    public function getPatterns(): array
    {
        return $this->patterns ?? $this->defaultPatterns;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setPatterns(mixed $patterns): self
    {
        $this->patterns = $this->assertPatternList($patterns);
        return $this;
    }

    protected function match(string $requestedName): ?string
    {
        foreach ($this->getPatterns() as $pattern) {
            if (preg_match($pattern, $requestedName, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @return array<array-key, string>
     * @throws Exception\InvalidArgumentException
     */
    private function assertPatternList(mixed $patterns): array
    {
        if ($patterns instanceof Traversable) {
            $patterns = iterator_to_array($patterns);
        }

        if (! is_array($patterns)) {
            throw new Exception\InvalidArgumentException('patterns must be array or Traversable');
        }

        /** @var array<array-key, string> */
        return $patterns;
    }

    /**
     * Resolve the configuration key and value for a requested service name.
     *
     * @return array{key: string, value: mixed}|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function find(ContainerInterface $container, string $requestedName): ?array
    {
        $key = $this->match($requestedName);
        if (null === $key) {
            return null;
        }

        if (array_key_exists($key, $this->configs)) {
            return ['key' => $key, 'value' => $this->configs[$key]];
        }

        $config = $container->has('config') ? $container->get('config') : [];
        if (! is_array($config) || ! array_key_exists($key, $config)) {
            return null;
        }

        return ['key' => $key, 'value' => $config[$key]];
    }

    /**
     * @param array<array-key, mixed>|null $options
     * @throws Exception\InvalidArgumentException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Override]
    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): mixed
    {
        $entry = $this->find($container, $requestedName) ?? throw new Exception\InvalidArgumentException(
            sprintf('Unable to resolve a configuration value for the requested service name "%s"', $requestedName),
        );

        $this->configs[$requestedName] = $entry['value'];
        $this->configs[$entry['key']]  = $entry['value'];

        return $entry['value'];
    }
}
