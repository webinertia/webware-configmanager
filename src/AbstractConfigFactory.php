<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use InvalidArgumentException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Override;
use Psl\Type\Exception\CoercionException;
use Psl\Type\TypeInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\ConfigManager\Container\ConfigService;
use Webware\ConfigManager\Pattern\PatternValidator;

use function array_key_exists;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * Resolves service names to configuration entries.
 *
 * An abstract factory in the laminas sense: a single instance creates any number of
 * related services, selecting each one by matching the requested name against the
 * configured patterns and returning the matching entry from the `config` service.
 *
 * @api
 */
final class AbstractConfigFactory implements AbstractFactoryInterface
{
    /** @var array<string, mixed> */
    private array $configs = [];

    /** @var list<string> */
    private array $defaultPatterns = [
        '#config[\._-](.*)$#i',
        '#^(.*)[\\\\\._-]config$#i',
    ];

    /** @var array<array-key, string>|null */
    private ?array $patterns = null;

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
        foreach (PatternValidator::validate($patterns) as $pattern) {
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
     * Resolve a configuration entry and shape it with a caller-supplied type.
     *
     * The source is always the container's `config` service, the same one canCreate()
     * and __invoke() read, so a type can never be applied to an arbitrary array. That
     * is what makes the return type meaningful: the caller states the shape it needs
     * and a mismatch is an error here rather than a failure at the point of use.
     *
     * The coerced value is deliberately not memoized. `__invoke()` caches the raw
     * entry, and writing a shaped value into that cache would change what a later
     * untyped call receives.
     *
     * @template T
     * @param TypeInterface<T> $type
     * @return T
     * @throws Exception\InvalidArgumentException If the entry is missing or does not match the type.
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function resolve(ContainerInterface $container, string $requestedName, TypeInterface $type): mixed
    {
        $entry = $this->find($container, $requestedName) ?? throw new Exception\InvalidArgumentException(
            sprintf('Unable to resolve a configuration value for the requested service name "%s"', $requestedName),
        );

        try {
            return $type->coerce($entry['value']);
        } catch (CoercionException $exception) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Configuration entry "%s" does not match the expected type: %s',
                $entry['key'],
                $exception->getMessage(),
            ), previous: $exception);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setPatterns(mixed $patterns): self
    {
        $this->patterns = PatternValidator::validate($patterns);
        return $this;
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

        if (array_key_exists($key, $this->configs) && null !== $this->configs[$key]) {
            return ['key' => $key, 'value' => $this->configs[$key]];
        }

        $config = ConfigService::entriesOf($container);
        if (! array_key_exists($key, $config) || null === $config[$key]) {
            return null;
        }

        return ['key' => $key, 'value' => $config[$key]];
    }

    private function match(string $requestedName): ?string
    {
        // A pattern that matches without a capturing group reports the whole match at
        // index 0 only, so $matches[1] would be undefined. The guard keeps that a
        // "no match" rather than an undefined-key warning, which a fail-on-warning
        // harness turns into a failure far from the pattern that caused it.
        foreach ($this->getPatterns() as $pattern) {
            if (! preg_match($pattern, $requestedName, $matches)) {
                continue;
            }

            $captured = $matches[1] ?? null;
            if (is_string($captured)) {
                return $captured;
            }
        }

        return null;
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
