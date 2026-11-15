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

abstract class AbstractWriter implements WriterInterface
{
    /**
     * @return mixed The value with every reachable Traversable converted to an array.
     */
    private static function expand(mixed $config): mixed
    {
        if (is_array($config)) {
            return self::mapValues($config);
        }

        if (! $config instanceof Traversable) {
            return $config;
        }

        // An aggregate that offers its own array form is trusted over iteration.
        if (! $config instanceof Iterator && method_exists($config, 'toArray')) {
            return $config->toArray();
        }

        /** @var Traversable<array-key, mixed> $config */
        return self::mapValues($config);
    }

    /**
     * @param iterable<array-key, mixed> $iterable
     * @return array<array-key, mixed>
     */
    // A merged config is untyped by nature, so values and keys stay mixed.
    // @mago-expect analysis:mixed-assignment
    private static function mapValues(iterable $iterable): array
    {
        $array = [];

        foreach ($iterable as $key => $value) {
            $array[$key] = self::expand($value);
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
    private static function normalize(mixed $config): array
    {
        try {
            return Type\mixed_dict()->coerce(self::expand($config));
        } catch (CoercionException) {
            throw new Exception\InvalidArgumentException(
                self::class . '::toString expects an array or Traversable config',
            );
        }
    }

    /**
     * toFile(): defined by Writer interface.
     *
     * The file is replaced atomically, so a reader sees either the previous
     * document or the new one and never a partial write.
     *
     * @see    WriterInterface::toFile()
     *
     * @param  string  $filename
     * @param  mixed   $config
     * @param  bool $exclusiveLock Ignored; retained for interface compatibility. An
     *                             advisory lock does not protect readers, because
     *                             they do not take one, so it is replaced by the
     *                             atomic rename.
     * @return void
     * @throws WriterException\InvalidArgumentException If the file name is empty.
     * @throws WriterException\RuntimeException If the file cannot be written.
     * @throws Exception\InvalidArgumentException If the config cannot be serialized.
     */
    #[Override]
    public function toFile($filename, $config, $exclusiveLock = true)
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
     * @param  mixed   $config
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function toString($config)
    {
        return $this->processConfig(self::normalize($config));
    }

    /**
     * @return string
     */
    abstract protected function processConfig(array $config);

    /**
     * Serialize the configuration for a specific destination.
     *
     * Writers that need to rewrite the document in terms of where it will be
     * written override this instead of overriding toFile(). The destination is
     * already validated and resolved by the time this is called, so an override can
     * rely on it naming the directory the file really lives in.
     *
     * @throws Exception\InvalidArgumentException
     */
    // $filename exists for an override to read; this implementation only forwards to
    // toString(). It is not underscore-prefixed because this project forbids that.
    // @mago-expect analysis:unused-parameter
    protected function toContent(mixed $config, string $filename): string
    {
        return $this->toString($config);
    }
}
