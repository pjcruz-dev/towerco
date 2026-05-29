<?php

declare(strict_types=1);

namespace App\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised when a domain module completes a significant state change (hook for listeners / projections).
 */
abstract class DomainEvent
{
    use Dispatchable;
    use SerializesModels;
}
