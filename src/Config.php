<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use ArrayAccess;
use Countable;
use Iterator;
use Override;
use Webware\ConfigManager\Merge\ConfigMerger;

use function array_key_exists;
use function count;
use function current;
use function is_array;
use function key;
use function next;
use function reset;

/**
 * Provides a property based interface to an array.
 * The data are read-only unless $allowModifications is set to true
 * on construction.
 *
 * Implements Countable, Iterator and ArrayAccess
 * to facilitate easy access to the data.
 *
 * @implements ArrayAccess<array-key, mixed>
 * @implements Iterator<array-key, mixed>
 * @api Extendable by design: nested arrays and merge() return `static`, so a subclass keeps
 *      its own type through both.
 */
class Config implements Countable, Iterator, ArrayAccess
{
    /**
     * Whether modifications to configuration data are allowed.
     */
    protected bool $allowModifications;

    /**
     * Data within the configuration.
     *
     * @var array<array-key, mixed>
     */
    protected array $data = [];

    /**
     * Used when unsetting values during iteration to ensure we do not skip
     * the next element.
     */
    protected bool $skipNextIteration = false;

    /**
     * Data is read-only unless $allowModifications is set to true
     * on construction.
     * @param array<array-key, mixed> $array
     */
    public function __construct(array $array, bool $allowModifications = false)
    {
        $this->allowModifications = $allowModifications;

        foreach ($array as $key => $value) {
            $this->data[$key] = is_array($value)
                ? $this->newNested($value, $this->allowModifications)
                : $value;
        }
    }

    /**
     * count(): defined by Countable interface.
     *
     * @see    Countable::count()
     */
    #[Override]
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * current(): defined by Iterator interface.
     *
     * @see    Iterator::current()
     */
    #[Override]
    public function current(): mixed
    {
        // Mutating this assignment arms the skip flag on every element, so every
        // foreach over a Config never advances and the suite hangs instead of
        // failing. That makes the mutant unmeasurable: Infection cannot complete its
        // run. The behaviour it targets is asserted directly by
        // ConfigTest::testIteratorPrimitivesAdvanceThePointer().
        // @infection-ignore-all
        $this->skipNextIteration = false;
        return current($this->data);
    }

    /**
     * Retrieve a value and return $default if there is no element set.
     */
    public function get(string|int $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        return $default;
    }

    /**
     * Returns whether this Config object is read only or not.
     */
    public function isReadOnly(): bool
    {
        return ! $this->allowModifications;
    }

    /**
     * key(): defined by Iterator interface.
     *
     * @see    Iterator::key()
     */
    #[Override]
    public function key(): int|string|null
    {
        return key($this->data);
    }

    /**
     * Merge another Config with this one.
     *
     * The rules are the ones {@see ConfigMerger} applies to arrays, which is the same
     * thing stated once: nested arrays are merged recursively, integer keys append, and
     * string keys overwrite. Both sides are reduced to arrays first, so a Config merges
     * exactly as the equivalent arrays would.
     *
     * The result is rebuilt rather than patched key by key, which is what lets the nested
     * construction stay behind {@see newNested()} for a subclass. A consequence is that
     * children are new objects: a Config held from before the merge does not observe it.
     *
     * Merging is a modification, so a read-only Config refuses it for the same reason
     * assigning to one does. To merge into configuration that was loaded read-only, such
     * as a cached config, build a mutable copy first.
     *
     * For duplicate keys, the following will be performed:
     * - Nested Configs will be recursively merged.
     * - Items in $merge with INTEGER keys will be appended.
     * - Items in $merge with STRING keys will overwrite current values.
     *
     * @throws Exception\RuntimeException If this Config is read only.
     */
    public function merge(Config $merge): static
    {
        $this->assertModifiable();

        $this->data = $this->newNested(
            ConfigMerger::merge($this->toArray(), $merge->toArray()),
            $this->allowModifications,
        )->data;

        return $this;
    }

    /**
     * next(): defined by Iterator interface.
     *
     * @see    Iterator::next()
     */
    #[Override]
    public function next(): void
    {
        if ($this->skipNextIteration) {
            $this->skipNextIteration = false;
            return;
        }

        // Removing this call leaves the pointer where it is, so every foreach over a
        // Config never advances and the suite hangs instead of failing. See the note
        // in current() for why the mutant cannot be measured.
        // @infection-ignore-all
        next($this->data);
    }

    /**
     * offsetExists(): defined by ArrayAccess interface.
     *
     * @see    ArrayAccess::offsetExists()
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * offsetGet(): defined by ArrayAccess interface.
     *
     * @see    ArrayAccess::offsetGet()
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    /**
     * offsetSet(): defined by ArrayAccess interface.
     *
     * @see    ArrayAccess::offsetSet()
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * offsetUnset(): defined by ArrayAccess interface.
     *
     * @see    ArrayAccess::offsetUnset()
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->__unset($offset);
    }

    /**
     * rewind(): defined by Iterator interface.
     *
     * @see    Iterator::rewind()
     */
    #[Override]
    public function rewind(): void
    {
        $this->skipNextIteration = false;
        reset($this->data);
    }

    /**
     * Prevent any more modifications being made to this instance.
     *
     * Useful after merge() has been used to merge multiple Config objects
     * into one object which should then not be modified again.
     */
    public function setReadOnly(): void
    {
        $this->allowModifications = false;

        /** @var mixed $value */
        foreach ($this->data as $value) {
            if (! $value instanceof self) {
                continue;
            }

            $value->setReadOnly();
        }
    }

    /**
     * Return an associative array of the stored data.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        $array = [];

        /** @var mixed $value */
        foreach ($this->data as $key => $value) {
            $array[$key] = $value instanceof self ? $value->toArray() : $value;
        }

        return $array;
    }

    /**
     * valid(): defined by Iterator interface.
     *
     * @see    Iterator::valid()
     */
    #[Override]
    public function valid(): bool
    {
        return null !== $this->key();
    }

    /**
     * Build the Config that holds a nested array.
     *
     * Children are Configs, not necessarily this subclass. A subclass whose constructor
     * takes something other than config data cannot be built from a nested array, so
     * construction is routed through here and an override supplies whatever a nested
     * value should be. {@see ConfigAggregator} is that case: it builds from a provider
     * list, so one of its nested values is an ordinary read-only Config.
     *
     * @param array<array-key, mixed> $array
     */
    protected function newNested(array $array, bool $allowModifications): self
    {
        return new static($array, $allowModifications);
    }

    /**
     * Refuse a modification when this Config was built read only.
     *
     * @throws Exception\RuntimeException If modifications are not allowed.
     */
    private function assertModifiable(): void
    {
        if (! $this->allowModifications) {
            throw new Exception\RuntimeException('Config is read only');
        }
    }

    /**
     * Deep clone of this instance to ensure that nested Webware\ConfigManager
     * instances are also cloned.
     */
    public function __clone(): void
    {
        $array = [];

        foreach ($this->data as $key => $value) {
            $array[$key] = $value instanceof self ? clone $value : $value;
        }

        $this->data = $array;
    }

    /**
     * Magic function so that $obj->value will work.
     */
    public function __get(mixed $name): mixed
    {
        // @mago-expect analysis:mixed-argument
        return $this->get($name);
    }

    /**
     * isset() overloading
     */
    public function __isset(mixed $name): bool
    {
        // @mago-expect analysis:mixed-argument
        return array_key_exists($name, $this->data) && null !== $this->data[$name];
    }

    /**
     * Set a value in the config.
     *
     * Only allow setting of a property if $allowModifications was set to true
     * on construction. Otherwise, throw an exception.
     *
     * A null name appends the value, which is what PHP passes for $config[].
     *
     * @throws Exception\RuntimeException
     */
    public function __set(string|int|null $name, mixed $value): void
    {
        $this->assertModifiable();

        if (is_array($value)) {
            $value = $this->newNested($value, allowModifications: true);
        }

        if (null === $name) {
            $this->data[] = $value;

            return;
        }

        $this->data[$name] = $value;
    }

    /**
     * unset() overloading
     *
     * @throws Exception\InvalidArgumentException
     */
    public function __unset(mixed $name): void
    {
        if (! $this->allowModifications) {
            throw new Exception\InvalidArgumentException('Config is read only');
        }

        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
            $this->skipNextIteration = true;
        }
    }
}
