<?php

declare(strict_types=1);

namespace Webware\ConfigManager\Processor;

/**
 * Selects which part of each queued entry {@see Queue::toArray()} returns.
 *
 * @api
 */
enum QueueContents
{
    /** The processors, in insertion order. */
    case Data;

    /** The priority of each entry, in insertion order. */
    case Priorities;

    /** Each entry as a `['data' => ..., 'priority' => ...]` array. */
    case Both;
}
