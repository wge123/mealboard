<?php

namespace App\Enums;

/**
 * Which lane a discovered candidate came down. The weekday limits bind the
 * scheduled lane only; the avoided ingredients bind both.
 */
enum DiscoveryLane: string
{
    case Scheduled = 'scheduled';
    case Request = 'request';
}
