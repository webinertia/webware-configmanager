<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Countable;
use IteratorAggregate;
use Override;
use SplPriorityQueue;
use Traversable;
use Webware\ConfigManager\Config;
use Webware\ConfigManager\Exception;

use function array_any;
use function array_values;
use function count;

/**
 * A priority ordered collection of processors.
 *
 * This replaces Laminas\Stdlib\PriorityQueue so the package no longer depends on
 * laminas/laminas-stdlib. A processor inserted with a higher priority runs first, and the
 * queue is iterated in that order without consuming it. Each method is a distinct queue
 * operation rather than a nested branch.
 *
 * @implements IteratorAggregate<int, ProcessorInterface>
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @api
 */
final class Queue implements ProcessorInterface, Countable, IteratorAggregate
{
    /** @var list<array{data: ProcessorInterface, priority: int}> */
    private array $items = [];

    /** @var SplPriorityQueue<int, ProcessorInterface>|null */
    private ?SplPriorityQueue $queue = null;

    public function contains(ProcessorInterface $datum): bool
    {
        return array_any(
            $this->items,
            /** @param array{data: ProcessorInterface, priority: int} $item */
            static fn(array $item): bool => $item['data'] === $datum,
        );
    }

    #[Override]
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Remove and return the highest priority processor.
     *
     * @return ProcessorInterface
     */
    public function extract(): ProcessorInterface
    {
        $value = $this->getQueue()->extract();

        $index    = null;
        $priority = null;

        foreach ($this->items as $key => $item) {
            if ($item['data'] !== $value) {
                continue;
            }

            if (null !== $priority && $item['priority'] <= $priority) {
                continue;
            }

            $priority = $item['priority'];
            $index    = $key;
        }

        if (null !== $index) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }

        return $value;
    }

    /**
     * Retrieve the processors in priority order.
     *
     * The inner queue is cloned because iterating an SplPriorityQueue extracts from it,
     * which would empty the queue on the first pass.
     *
     * @return Traversable<int, ProcessorInterface>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        return clone $this->getQueue();
    }

    public function hasPriority(int $priority): bool
    {
        return array_any(
            $this->items,
            /** @param array{data: ProcessorInterface, priority: int} $item */
            static fn(array $item): bool => $item['priority'] === $priority,
        );
    }

    /**
     * Insert a processor into the queue.
     *
     * Priority defaults to 1 if none is provided.
     *
     * @return $this
     */
    public function insert(ProcessorInterface $data, int $priority = 1): self
    {
        $this->items[] = [
            'data'     => $data,
            'priority' => $priority,
        ];

        if ($this->queue instanceof SplPriorityQueue) {
            $this->queue->insert($data, $priority);
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return 0 === count($this->items);
    }

    /**
     * Process the whole config structure with each parser in the queue.
     *
     * @throws Exception\InvalidArgumentException
     */
    #[Override]
    public function process(Config $config): Config
    {
        if ($config->isReadOnly()) {
            throw new Exception\InvalidArgumentException('Cannot process config because it is read-only');
        }

        foreach ($this as $parser) {
            $parser->process($config);
        }

        return $config;
    }

    /**
     * Process a single value
     */
    #[Override]
    public function processValue(mixed $value): mixed
    {
        foreach ($this as $parser) {
            $value = $parser->processValue($value);
        }

        return $value;
    }

    /**
     * Remove the first processor identical to the one provided.
     *
     * @return bool False if the processor was not found, true otherwise.
     */
    public function remove(ProcessorInterface $datum): bool
    {
        $removed = false;

        foreach ($this->items as $key => $item) {
            if ($item['data'] !== $datum) {
                continue;
            }

            unset($this->items[$key]);
            $removed = true;

            break;
        }

        if (! $removed) {
            return false;
        }

        $this->items = array_values($this->items);
        $this->queue = null;

        return true;
    }

    /**
     * @return ($contents is QueueContents::Both
     *     ? list<array{data: ProcessorInterface, priority: int}>
     *     : ($contents is QueueContents::Priorities ? list<int> : list<ProcessorInterface>))
     */
    public function toArray(QueueContents $contents = QueueContents::Data): array
    {
        return match ($contents) {
            QueueContents::Both       => $this->items,
            QueueContents::Priorities => $this->priorities(),
            QueueContents::Data       => $this->data(),
        };
    }

    /**
     * Peek at the highest priority processor without removing it.
     *
     * @return ProcessorInterface
     */
    public function top(): ProcessorInterface
    {
        $queue = clone $this->getQueue();

        return $queue->top();
    }

    /**
     * @return list<ProcessorInterface>
     */
    private function data(): array
    {
        $data = [];

        foreach ($this->items as $item) {
            $data[] = $item['data'];
        }

        return $data;
    }

    /**
     * @return SplPriorityQueue<int, ProcessorInterface>
     */
    private function getQueue(): SplPriorityQueue
    {
        if (! $this->queue instanceof SplPriorityQueue) {
            $this->queue = new SplPriorityQueue();

            foreach ($this->items as $item) {
                $this->queue->insert($item['data'], $item['priority']);
            }
        }

        return $this->queue;
    }

    /**
     * @return list<int>
     */
    private function priorities(): array
    {
        $priorities = [];

        foreach ($this->items as $item) {
            $priorities[] = $item['priority'];
        }

        return $priorities;
    }
}
