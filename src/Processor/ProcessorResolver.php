<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

use Webware\ConfigManager\Container\ProviderFactory;
use Webware\ConfigManager\Exception;

use function get_debug_type;
use function is_callable;
use function is_string;
use function sprintf;

/**
 * Resolves a configured pre-processor or post-processor to something callable.
 *
 * The contract is the aggregator's: a class-string is instantiated with no arguments and
 * must then be callable, and a callable is used verbatim. Keeping it identical matters,
 * because a processor written for `Laminas\ConfigAggregator` has to work unchanged.
 *
 * @internal
 */
final class ProcessorResolver
{
    /**
     * @param class-string|callable $processor
     * @throws Exception\InvalidArgumentException
     */
    public function resolve(mixed $processor): callable
    {
        if (is_string($processor)) {
            $processor = ProviderFactory::create(
                class: $processor,
                label: 'Config processor',
            );
        }

        if (is_callable($processor)) {
            /** @var callable $processor */
            return $processor;
        }

        throw new Exception\InvalidArgumentException(sprintf(
            'A config processor must be a class-string or a callable; received "%s"',
            get_debug_type($processor),
        ));
    }
}
