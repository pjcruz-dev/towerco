<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}.rollouts', function ($user, string $tenantId): bool {
    if ($user === null) {
        return false;
    }

    if (function_exists('tenancy') && tenancy()->initialized) {
        return (string) tenant('id') === (string) $tenantId;
    }

    return false;
});
