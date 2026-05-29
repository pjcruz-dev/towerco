<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum tokens for tenant users live in the tenant database (stancl tenancy).
 */
class TenantPersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'tenant';

    protected $table = 'personal_access_tokens';
}
