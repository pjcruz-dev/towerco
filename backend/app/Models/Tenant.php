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
            'trial_ends_at',
            'past_due_grace_ends_at',
            'canceled_at',
            'subscription_locked_at',
            'operator_access_mode',
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_price_id',
            'billing_overrides',
            'enabled_modules',
            'seat_limit',
            'billing_meter_starts_at',
            'billing_interval',
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
            'billing_overrides' => 'array',
            'enabled_modules' => 'array',
            'seat_limit' => 'integer',
            'billing_meter_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'past_due_grace_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'subscription_locked_at' => 'datetime',
        ];
    }
}
