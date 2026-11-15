<?php

declare(strict_types=1);

namespace WebwareTest\ConfigManager\Writer\TestAssets;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

use function array_keys;

/**
 * An aggregate that iterates different data than it reports through toArray(), so a test
 * can tell which of the two the writer used.
 *
 * @implements IteratorAggregate<string, string>
 */
final class AggregateWithToArray implements IteratorAggregate
{
    /**
     * @param array<string, string> $iterated
     */
    public function __construct(
        private readonly array $iterated,
    ) {}

    /**
     * @return Traversable<string, string>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->iterated);
    }

    /**
     * @return list<string>
     */
    public function iteratedKeys(): array
    {
        return array_keys($this->iterated);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['value' => 'fromToArray'];
    }
}
