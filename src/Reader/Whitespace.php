<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Reader;

/**
 * Whether surrounding whitespace is stripped from parsed keys and values.
 *
 * @api
 */
enum Whitespace
{
    /** Strip leading and trailing whitespace from keys and values. */
    case Trim;

    /** Leave keys and values exactly as they appear in the source. */
    case Keep;
}
