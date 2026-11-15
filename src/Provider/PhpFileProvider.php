<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

use Generator;
use Psl\Type;
use Webware\ConfigManager\Exception;
use Webware\ConfigManager\File\Glob;
use Webware\ConfigManager\Shape;

use function sprintf;

/**
 * Provides the configuration arrays returned by every PHP file matching a pattern.
 *
 * This is the drop-in equivalent of Laminas\ConfigAggregator\PhpFileProvider, and it
 * keeps the aggregator's contract rather than routing through a reader: the file is
 * included and whatever it returns is the configuration. So a configuration file is
 * plain PHP, and nothing has to be registered for it to be read.
 *
 * The pattern is matched by {@see Glob}, which reproduces the layered precedence the
 * aggregator relied on, so an application's existing pattern means the same thing here.
 *
 * @api
 */
final class PhpFileProvider
{
    /**
     * @throws Exception\InvalidArgumentException
     */
    public function __construct(
        private readonly string $pattern,
    ) {
        // matches(), not coerce(): coercion would accept a numeric path fragment and
        // silently rewrite the pattern, hiding the caller's mistake. The declared string
        // type leaves empty as the reachable failure.
        if (! Type\non_empty_string()->matches($pattern)) {
            throw new Exception\InvalidArgumentException('A config file pattern must not be empty');
        }
    }

    /**
     * Yield one configuration array per matching file, in pattern order.
     *
     * A file that does not return an array is rejected here rather than by the caller,
     * because this is the layer that knows which file it was.
     *
     * @return Generator<int, array<array-key, mixed>>
     * @throws Exception\InvalidArgumentException
     */
    public function __invoke(): Generator
    {
        foreach (Glob::match($this->pattern) as $file) {
            // Included positionally into the argument so the file's untyped return
            // reaches the check without being stored in a variable first.
            yield Shape::array(
                include $file,
                sprintf('Config file "%s"', $file),
            );
        }
    }
}
