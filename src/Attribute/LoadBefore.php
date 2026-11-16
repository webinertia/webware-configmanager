<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Attribute;

use Attribute;

use function array_values;

/**
 * Declares that this config provider must be merged before the listed providers.
 *
 * The mirror of {@see LoadAfter}. Use whichever reads closer to the reason: a package
 * that must get out of the way declares `LoadBefore`, and a package that must win
 * declares `LoadAfter`.
 *
 * Both may be present, and both may be repeated, so a provider can state several
 * relationships without collapsing them into one list.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class LoadBefore
{
    /** @var list<class-string> */
    public readonly array $providers;

    /** @param class-string ...$providers */
    public function __construct(string ...$providers)
    {
        $this->providers = array_values($providers);
    }
}
