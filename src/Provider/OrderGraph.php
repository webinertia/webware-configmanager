<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

use Webware\ConfigManager\Exception;

use function array_column;
use function array_diff;
use function count;
use function implode;
use function sprintf;

/**
 * Ordering constraints between provider positions, and a stable sort over them.
 *
 * A position is the entry's index in the caller's list, so two instances of one class
 * stay distinct. A declaration names a class, and every position holding that class is
 * ordered relative to the declaring position.
 *
 * The sort is a stable topological sort: each pass takes the earliest remaining position
 * whose requirements have all been emitted. A provider with no constraint is therefore
 * never moved ahead of one that precedes it, which is what makes applying this
 * unconditionally safe.
 *
 * The pass count is bounded by the node count, because every pass emits exactly one node
 * or reports a cycle. That bound is what stops a broken constraint from spinning.
 *
 * @internal
 *
 * @type Node array{name: string, entry: class-string|callable}
 */
final class OrderGraph
{
    private readonly PositionIndex $index;

    /** @var array<int, list<int>> Position to the positions it must follow. */
    private array $edges = [];

    /** @param array<int, Node> $nodes One node per position, in the caller's order. */
    public function __construct(
        private readonly array $nodes,
    ) {
        $this->index = new PositionIndex($nodes);
    }

    /**
     * Record that a position must be emitted after every position holding the target.
     *
     * A target the list does not contain contributes nothing, and a target naming the
     * position's own class contributes nothing either, so a self-reference is never read
     * as a cycle.
     *
     * @param class-string $target
     */
    public function after(int $position, string $target): void
    {
        if ($this->index->nameOf($position) === $target) {
            return;
        }

        foreach ($this->index->of($target) as $targetPosition) {
            $this->edges[$position][] = $targetPosition;
        }
    }

    /**
     * Record that a position must be emitted before every position holding the target.
     *
     * @param class-string $target
     */
    public function before(int $position, string $target): void
    {
        if ($this->index->nameOf($position) === $target) {
            return;
        }

        foreach ($this->index->of($target) as $targetPosition) {
            $this->edges[$targetPosition][] = $position;
        }
    }

    /**
     * Return the entries in the order their constraints allow.
     *
     * @return list<class-string|callable>
     * @throws Exception\InvalidArgumentException If the constraints form a cycle.
     */
    public function sorted(): array
    {
        $remaining = $this->nodes;
        /** @var list<int> $emitted */
        $emitted = [];
        /** @var list<class-string|callable> $sorted */
        $sorted = [];

        for ($pass = 0, $total = count($remaining); $pass < $total; $pass++) {
            $next = $this->nextSatisfied($remaining, $emitted);

            if (null === $next) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Config provider ordering declarations form a cycle between: %s',
                    implode(', ', array_column($remaining, 'name')),
                ));
            }

            $sorted[]  = $next['node']['entry'];
            $emitted[] = $next['position'];
            unset($remaining[$next['position']]);
        }

        return $sorted;
    }

    /**
     * Find the earliest remaining position whose requirements have all been emitted.
     *
     * @param array<int, Node> $remaining
     * @param list<int> $emitted
     * @return null|array{position: int, node: Node}
     */
    private function nextSatisfied(array $remaining, array $emitted): ?array
    {
        foreach ($remaining as $position => $node) {
            if ([] !== array_diff($this->edges[$position] ?? [], $emitted)) {
                continue;
            }

            return ['position' => $position, 'node' => $node];
        }

        return null;
    }
}
