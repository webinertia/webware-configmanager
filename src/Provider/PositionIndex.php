<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

/**
 * Finds the positions in a provider list that hold a given class.
 *
 * A declaration names a class, and the list may hold that class more than once, so a
 * lookup returns every matching position. Keeping this separate from the ordering itself
 * means {@see OrderGraph} only has to think about what must come before what.
 *
 * @internal
 *
 * @import-type Node from OrderGraph
 */
final class PositionIndex
{
    /** @param array<int, Node> $nodes */
    public function __construct(
        private readonly array $nodes,
    ) {}

    /**
     * The name declared for a position, or null when the position does not exist.
     */
    public function nameOf(int $position): ?string
    {
        return $this->nodes[$position]['name'] ?? null;
    }

    /**
     * @param class-string $target
     * @return list<int>
     */
    public function of(string $target): array
    {
        $positions = [];

        foreach ($this->nodes as $position => $node) {
            if ($node['name'] !== $target) {
                continue;
            }

            $positions[] = $position;
        }

        return $positions;
    }
}
