<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /**
     * Real DB columns (everything else is stored in the virtual `data` JSON column).
     */
    public static function getCustomColumns(): array
    {
        return array_merge(parent::getCustomColumns(), [
            'created_at',
            'updated_at',
            'mfa_required',
            'theme_tokens',
            'plan_tier',
            'subscription_status',
            'seat_limit',
            'slug',
            'brand_domain',
            'environment',
            'tco_sequence_prefix',
            'parent_tenant_id',
        ]);
    }

    protected function casts(): array
    {
        return [
            'mfa_required' => 'boolean',
            'theme_tokens' => 'array',
            'seat_limit' => 'integer',
        ];
    }
}
