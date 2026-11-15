<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Writer;

use Iterator;
use Override;
use Psl\Type;
use Psl\Type\Exception\CoercionException;
use Traversable;
use Webware\ConfigManager\Exception;
use Webware\FileWriter\Exception as WriterException;
use Webware\FileWriter\FileWriter;

use function is_array;
use function method_exists;

/**
 * @api
 */
abstract class AbstractWriter implements WriterInterface
{
    /**
     * toFile(): defined by Writer interface.
     *
     * The file is replaced atomically, so a reader sees either the previous
     * document or the new one and never a partial write. An advisory lock is not
     * used because readers do not take one, so it would protect nothing; the atomic
     * rename is what makes a partial write unobservable.
     *
     * @see    WriterInterface::toFile()
     *
     * @throws WriterException\InvalidArgumentException If the file name is empty.
     * @throws WriterException\RuntimeException If the file cannot be written.
     * @throws Exception\InvalidArgumentException If the config cannot be serialized.
     */
    #[Override]
    public function toFile(string $filename, mixed $config): void
    {
        // Resolved before serialization: a writer that builds paths out of the
        // destination must name the directory the file really lives in, which is the
        // link target rather than the link.
        $resolved = FileWriter::resolve($filename);

        FileWriter::write($resolved, $this->toContent($config, $resolved));
    }

    /**
     * toString(): defined by Writer interface.
     *
     * @see    WriterInterface::toString()
     *
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function toString(mixed $config): string
    {
        return $this->processConfig($this->normalize($config));
    }

    /**
     * @param array<array-key, mixed> $config
     */
    abstract protected function processConfig(array $config): string;

    /**
     * Serialize the configuration for a specific destination.
     *
     * Writers that need to rewrite the document in terms of where it will be
     * written override this instead of overriding toFile(). The destination is
     * already validated and resolved by the time this is called, so an override can
     * rely on it naming the directory the file really lives in.
     *
     * @param string $filename exists for an override to read; this implementation only
     *     forwards to toString(). It is not underscore-prefixed because this project
     *     forbids that.
     * @throws Exception\InvalidArgumentException
     * @mago-expect analysis:unused-parameter
     */
    protected function toContent(mixed $config, string $filename): string
    {
        return $this->toString($config);
    }

    /**
     * @return mixed The value with every reachable Traversable converted to an array.
     */
    private function expand(mixed $config): mixed
    {
        if (is_array($config)) {
            return $this->mapValues($config);
        }

        if (! $config instanceof Traversable) {
            return $config;
        }

        // An aggregate that offers its own array form is trusted over iteration.
        if (! $config instanceof Iterator && method_exists($config, 'toArray')) {
            return $config->toArray();
        }

        /** @var Traversable<array-key, mixed> $config */
        return $this->mapValues($config);
    }

    /**
     * A merged config is untyped by nature, so values and keys stay mixed.
     *
     * @param iterable<array-key, mixed> $iterable
     * @return array<array-key, mixed>
     * @mago-expect analysis:mixed-assignment
     */
    private function mapValues(iterable $iterable): array
    {
        $array = [];

        foreach ($iterable as $key => $value) {
            $array[$key] = $this->expand($value);
        }

        return $array;
    }

    /**
     * Converts a configuration value into a plain array.
     *
     * This replaces Laminas\Stdlib\ArrayUtils::iteratorToArray(). Nesting is followed,
     * because a Config holds its children as Config instances and the writers only
     * serialize plain arrays.
     *
     * @throws Exception\InvalidArgumentException If the value is not an array or Traversable.
     * @return array<array-key, mixed>
     */
    private function normalize(mixed $config): array
    {
        try {
            return Type\mixed_dict()->coerce($this->expand($config));
        } catch (CoercionException) {
            throw new Exception\InvalidArgumentException(
                static::class . '::toString expects an array or Traversable config',
            );
        }
    }
}
