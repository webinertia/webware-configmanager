<?php

declare(strict_types=1);

namespace Webware\ConfigManager;

use ArrayAccess;
use Countable;
use Iterator;
// @codingStandardsIgnoreLine
use Override;
use ReturnTypeWillChange;

use function array_key_exists;
use function count;
use function current;
use function is_array;
use function is_int;
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
 */
class Config implements Countable, Iterator, ArrayAccess
{
    /**
     * Whether modifications to configuration data are allowed.
     *
     * @var bool
     */
    protected $allowModifications;

    /**
     * Data within the configuration.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Used when unsetting values during iteration to ensure we do not skip
     * the next element.
     *
     * @var bool
     */
    protected $skipNextIteration;

    /**
     * Data is read-only unless $allowModifications is set to true
     * on construction.
     *
     * @param  bool $allowModifications
     */
    public function __construct(array $array, $allowModifications = false)
    {
        $this->allowModifications = $allowModifications;

        foreach ($array as $key => $value) {
            $this->data[$key] = is_array($value)
                ? new static($value, $this->allowModifications)
                : $value;
        }
    }

    /**
     * count(): defined by Countable interface.
     *
     * @see    Countable::count()
     *
     * @return int
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function count()
    {
        return count($this->data);
    }

    /**
     * current(): defined by Iterator interface.
     *
     * @see    Iterator::current()
     *
     * @return mixed
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function current()
    {
        $this->skipNextIteration = false;
        return current($this->data);
    }

    /**
     * Retrieve a value and return $default if there is no element set.
     *
     * @param  string $name
     * @param  mixed  $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        return $default;
    }

    /**
     * Returns whether this Config object is read only or not.
     *
     * @return bool
     */
    public function isReadOnly()
    {
        return ! $this->allowModifications;
    }

    /**
     * key(): defined by Iterator interface.
     *
     * @see    Iterator::key()
     *
     * @return mixed
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function key()
    {
        return key($this->data);
    }

    /**
     * Merge another Config with this one.
     *
     * For duplicate keys, the following will be performed:
     * - Nested Configs will be recursively merged.
     * - Items in $merge with INTEGER keys will be appended.
     * - Items in $merge with STRING keys will overwrite current values.
     *
     * @return self
     */
    public function merge(Config $merge)
    {
        /** @var Config $value */
        foreach ($merge as $key => $value) {
            if (! array_key_exists($key, $this->data)) {
                $this->data[$key] = $value instanceof self
                    ? new static($value->toArray(), $this->allowModifications)
                    : $value;
                continue;
            }

            if (is_int($key)) {
                $this->data[] = $value;
                continue;
            }

            if ($value instanceof self && $this->data[$key] instanceof self) {
                $this->data[$key]->merge($value);
                continue;
            }

            $this->data[$key] = $value instanceof self
                ? new static($value->toArray(), $this->allowModifications)
                : $value;
        }

        return $this;
    }

    /**
     * next(): defined by Iterator interface.
     *
     * @see    Iterator::next()
     *
     * @return void
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function next()
    {
        if ($this->skipNextIteration) {
            $this->skipNextIteration = false;
            return;
        }

        next($this->data);
    }

    /**
     * offsetExists(): defined by ArrayAccess interface.
     *
     * @see    ArrayAccess::offsetExists()
     *
     * @param  mixed $offset
     * @return bool
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * offsetGet(): defined by ArrayAccess interface.
     *
     * @see    ArrayAccess::offsetGet()
     *
     * @param  mixed $offset
     * @return mixed
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * offsetSet(): defined by ArrayAccess interface.
     *
     * @see    ArrayAccess::offsetSet()
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @return void
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * offsetUnset(): defined by ArrayAccess interface.
     *
     * @see    ArrayAccess::offsetUnset()
     *
     * @param  mixed $offset
     * @return void
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    /**
     * rewind(): defined by Iterator interface.
     *
     * @see    Iterator::rewind()
     *
     * @return void
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function rewind()
    {
        $this->skipNextIteration = false;
        reset($this->data);
    }

    /**
     * Prevent any more modifications being made to this instance.
     *
     * Useful after merge() has been used to merge multiple Config objects
     * into one object which should then not be modified again.
     *
     * @return void
     */
    public function setReadOnly()
    {
        $this->allowModifications = false;

        /** @var Config $value */
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
     * @return array
     */
    public function toArray()
    {
        $array = [];
        $data  = $this->data;

        /** @var self $value */
        foreach ($data as $key => $value) {
            $array[$key] = $value instanceof self ? $value->toArray() : $value;
        }

        return $array;
    }

    /**
     * valid(): defined by Iterator interface.
     *
     * @see    Iterator::valid()
     *
     * @return bool
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function valid()
    {
        return $this->key() !== null;
    }

    /**
     * Deep clone of this instance to ensure that nested Webware\ConfigManagers are also
     * cloned.
     *
     * @return void
     */
    public function __clone()
    {
        $array = [];

        foreach ($this->data as $key => $value) {
            $array[$key] = $value instanceof self ? clone $value : $value;
        }

        $this->data = $array;
    }

    /**
     * Magic function so that $obj->value will work.
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * isset() overloading
     *
     * @param  string $name
     * @return bool
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Set a value in the config.
     *
     * Only allow setting of a property if $allowModifications  was set to true
     * on construction. Otherwise, throw an exception.
     *
     * @param  string $name
     * @param  mixed  $value
     * @return void
     * @throws Exception\RuntimeException
     */
    public function __set($name, $value)
    {
        if (! $this->allowModifications) {
            throw new Exception\RuntimeException('Config is read only');
        }

        if (is_array($value)) {
            $value = new static($value, true);
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
     * @param  string $name
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function __unset($name)
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
