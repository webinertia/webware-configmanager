<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Merge;

/**
 * What a {@see MergeDirective} asks the merger to do with a key.
 *
 * @api
 */
enum MergeDirectiveKind
{
    /** Drop the key from the merged result. */
    case Remove;

    /** Use the carried value verbatim, without recursing into it. */
    case Replace;
}
