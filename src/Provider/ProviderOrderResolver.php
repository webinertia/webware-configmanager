<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

use Webware\ConfigManager\Exception;

use function get_debug_type;
use function is_string;

/**
 * Orders a provider list by the declarations the providers carry.
 *
 * The order is the one thing a provider list cannot express reliably, because
 * `laminas-component-installer` prepends whatever it writes, so a package installed last
 * lands first and loses every key it shares with a package it builds on. A provider can
 * declare {@see \Webware\ConfigManager\Attribute\LoadAfter} or
 * {@see \Webware\ConfigManager\Attribute\LoadBefore}, and the declaration decides.
 *
 * A provider that declares nothing keeps the position the caller gave it, so a list with
 * no declarations produces exactly the order it produced before this class existed. That
 * is what makes the behaviour safe to apply unconditionally.
 *
 * A declaration naming a class the list does not contain is ignored: a package cannot
 * know which of its peers the host application installed, so a dangling reference is
 * ordinary. A declaration that cannot be satisfied because the requirements loop is
 * reported instead, because silently picking a winner is how a shared key ends up
 * resolved by install order again.
 *
 * @api
 */
final class ProviderOrderResolver
{
    /**
     * Build the constraint graph by reading every entry's declarations.
     *
     * @param array<int, class-string|callable> $entries
     */
    private static function graph(array $entries): OrderGraph
    {
        $nodes = [];

        foreach ($entries as $entry) {
            $nodes[] = ['name' => self::nameOf($entry), 'entry' => $entry];
        }

        $graph = new OrderGraph($nodes);

        foreach ($entries as $position => $entry) {
            foreach (DeclarationReader::read($entry) as $declaration) {
                if (Relation::After === $declaration->relation) {
                    $graph->after($position, $declaration->target);

                    continue;
                }

                $graph->before($position, $declaration->target);
            }
        }

        return $graph;
    }

    /**
     * Name an entry the way a declaration elsewhere has to refer to it.
     *
     * A class-string is its own name. An instance is named by its class, because nothing
     * can name an instance.
     */
    private static function nameOf(mixed $entry): string
    {
        return is_string($entry) ? $entry : get_debug_type($entry);
    }

    /**
     * Return the providers in the order their declarations require.
     *
     * @param iterable<class-string|callable> $providers
     * @return list<class-string|callable>
     * @throws Exception\InvalidArgumentException If the declarations form a cycle.
     */
    public function order(iterable $providers): array
    {
        /** @var array<int, class-string|callable> $entries */
        $entries = [];

        foreach ($providers as $provider) {
            $entries[] = $provider;
        }

        // A stable sort over zero constraints returns the caller's order unchanged, so an
        // unconstrained list needs no special case.
        return self::graph($entries)->sorted();
    }

    /**
     * Return the providers in the order their declarations require.
     *
     * `__invoke` has the shape of a Laminas config-aggregator pre-processor, so an
     * instance can be passed straight into `preProcessors` by a consumer still using that
     * class, and used anywhere a `callable(iterable): iterable` is expected.
     *
     * @param iterable<class-string|callable> $providers
     * @return list<class-string|callable>
     * @throws Exception\InvalidArgumentException If the declarations form a cycle.
     */
    public function __invoke(iterable $providers): array
    {
        return $this->order($providers);
    }
}
