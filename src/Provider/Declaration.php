<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

/**
 * One ordering requirement read from a provider.
 *
 * A class-string is stored rather than a resolved reference, because the target is often
 * a provider the host application does not install, and a missing target is ignored
 * rather than reported.
 *
 * @internal
 */
final class Declaration
{
    /** @param class-string $target */
    public function __construct(
        public readonly Relation $relation,
        public readonly string $target,
    ) {}
}
