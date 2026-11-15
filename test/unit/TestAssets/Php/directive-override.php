<?php

declare(strict_types=1);

use Webware\ConfigManager\Merge\MergeDirective;

return [
    'drop'    => MergeDirective::remove(),
    'section' => MergeDirective::replace(['replacement' => 'value']),
    'added'   => MergeDirective::replace('new'),
];
