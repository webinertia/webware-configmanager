<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Provider;

/** The direction of an ordering declaration. */
enum Relation
{
    /** The provider must be merged after the target. */
    case After;

    /** The provider must be merged before the target. */
    case Before;
}
