<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Attribute;

use Attribute;

use function array_values;

/**
 * Declares that this config provider must be merged after the listed providers.
 *
 * Order decides which provider wins a shared key, and `laminas-component-installer`
 * prepends every provider it writes to `config/config.php`, so a package installed last
 * loads first and loses. Declaring the requirement on the provider puts the decision
 * where the knowledge actually is: the package that must override something.
 *
 * The declaration is inert when this package is not installed. A PHP attribute is only
 * checked against its class when the attribute is instantiated, so a package can name
 * this attribute in a `use` statement without requiring the dependency, and the class
 * still loads.
 *
 * Targets a provider the application does not use are ignored rather than rejected,
 * because a package cannot know which of its peers the host application installed.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class LoadAfter
{
    /** @var list<class-string> */
    public readonly array $providers;

    /** @param class-string ...$providers */
    public function __construct(string ...$providers)
    {
        $this->providers = array_values($providers);
    }
}
