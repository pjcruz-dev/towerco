<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Billing\Support\TenantBillingOverridesValidator;
use App\Modules\Billing\Services\StripeBillingConfig;
use App\Modules\Billing\Services\TenantPlanDowngradeGuard;
use App\Modules\Billing\Services\TenantSubscriptionLifecycleService;
use App\Modules\Identity\Services\MfaService;
use App\Modules\Platform\Support\TenantThemeTokensValidator;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
use App\Modules\Tenancy\Services\TenantModuleRbacSyncService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use App\Modules\Tenancy\Support\TenantEnabledModulesValidator;
use App\Modules\Tenancy\Support\TenantOperatorAccessMode;
use Illuminate\Support\Facades\DB;

final class PlatformTenantSettingsService
{
    public function __construct(
        private readonly TenantBillingAuditLogger $billingAudit,
        private readonly PlatformTenantAuditLogger $platformAudit,
        private readonly TenantPlanDowngradeGuard $planDowngradeGuard,
        private readonly TenantSubscriptionLifecycleService $subscriptions,
        private readonly MfaService $mfa,
        private readonly TenantEnabledModulesResolver $enabledModulesResolver,
        private readonly TenantModuleRbacSyncService $moduleRbacSync,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(Tenant $tenant, array $data, ?User $actor): array
    {
        $billingBefore = $this->billingSnapshot($tenant);
        $settingsBefore = $this->settingsSnapshot($tenant);

        if (array_key_exists('mfa_required', $data)) {
            $tenant->mfa_required = (bool) $data['mfa_required'];
            $this->mfa->forgetTenantPolicyCache((string) $tenant->id);
        }

        if (array_key_exists('theme_tokens', $data)) {
            if ($data['theme_tokens'] === null) {
                $tenant->theme_tokens = null;
            } else {
                $tenant->theme_tokens = TenantThemeTokensValidator::validate($data['theme_tokens']);
            }
        }

        $downgradeWarnings = $this->planDowngradeGuard->assertPlanTierChangeAllowed(
            $tenant,
            $billingBefore['plan_tier'],
            $data,
        );

        if (array_key_exists('plan_tier', $data)) {
            $tenant->plan_tier = (string) $data['plan_tier'];
        }
        if (array_key_exists('subscription_status', $data) || array_key_exists('trial_ends_at', $data) || array_key_exists('past_due_grace_ends_at', $data)) {
            $this->subscriptions->applyPlatformUpdate($tenant, $data);
        }
        if (array_key_exists('seat_limit', $data)) {
            $tenant->seat_limit = (int) $data['seat_limit'];
            if (is_array($tenant->billing_overrides) && array_key_exists('seat_limit', $tenant->billing_overrides)) {
                $overrides = $tenant->billing_overrides;
                unset($overrides['seat_limit']);
                $tenant->billing_overrides = $overrides === [] ? null : $overrides;
            }
        }
        if (array_key_exists('billing_meter_starts_at', $data)) {
            $tenant->billing_meter_starts_at = $data['billing_meter_starts_at'] !== null
                ? \Illuminate\Support\Carbon::parse((string) $data['billing_meter_starts_at'])
                : null;
        }
        if (array_key_exists('billing_interval', $data)) {
            $tenant->billing_interval = (string) $data['billing_interval'];
        }
        if (array_key_exists('billing_overrides', $data)) {
            $tenant->billing_overrides = TenantBillingOverridesValidator::validate($data['billing_overrides']);
        }

        if (array_key_exists('operator_access_mode', $data)) {
            $tenant->operator_access_mode = TenantOperatorAccessMode::normalize($data['operator_access_mode']);
        }

        $modulesChanged = false;
        if (array_key_exists('enabled_modules', $data)) {
            $tenant->enabled_modules = TenantEnabledModulesValidator::validate(
                $data['enabled_modules'],
                $this->enabledModulesResolver,
            );
            $modulesChanged = json_encode($settingsBefore['enabled_modules'] ?? null)
                !== json_encode($tenant->enabled_modules);
        }

        DB::transaction(function () use ($tenant, $billingBefore, $settingsBefore, $actor): void {
            $tenant->save();

            $billingAfter = $this->billingSnapshot($tenant);
            $billingChanges = $this->diffSnapshots($billingBefore, $billingAfter);
            $this->billingAudit->log($tenant, $actor, $billingChanges);

            $settingsAfter = $this->settingsSnapshot($tenant);
            $settingsChanges = $this->diffSnapshots($settingsBefore, $settingsAfter);

            $mfaChanges = $this->pickChanges($settingsChanges, ['mfa_required']);
            if ($mfaChanges !== []) {
                $this->platformAudit->log(
                    PlatformTenantAuditEventType::TENANT_MFA_UPDATED,
                    $tenant,
                    $actor,
                    $mfaChanges,
                );
            }

            $brandingChanges = $this->pickChanges($settingsChanges, ['theme_tokens']);
            if ($brandingChanges !== []) {
                $this->platformAudit->log(
                    PlatformTenantAuditEventType::TENANT_BRANDING_UPDATED,
                    $tenant,
                    $actor,
                    $brandingChanges,
                );
            }

            $moduleChanges = $this->pickChanges($settingsChanges, ['enabled_modules']);
            if ($moduleChanges !== []) {
                $this->platformAudit->log(
                    PlatformTenantAuditEventType::TENANT_MODULES_UPDATED,
                    $tenant,
                    $actor,
                    $moduleChanges,
                );
            }

            $accessChanges = $this->pickChanges($settingsChanges, ['operator_access_mode']);
            if ($accessChanges !== []) {
                $this->platformAudit->log(
                    PlatformTenantAuditEventType::TENANT_ACCESS_UPDATED,
                    $tenant,
                    $actor,
                    $accessChanges,
                );
            }
        });

        if ($modulesChanged) {
            $this->moduleRbacSync->syncForTenant($tenant);
        }

        $subscription = $this->subscriptions->snapshot($tenant);

        return [
            'tenant_id' => $tenant->id,
            'mfa_required' => (bool) $tenant->mfa_required,
            'plan_tier' => (string) $tenant->plan_tier,
            'subscription_status' => (string) $tenant->subscription_status,
            'seat_limit' => (int) $tenant->seat_limit,
            'billing_meter_starts_at' => $tenant->billing_meter_starts_at?->toIso8601String(),
            'billing_interval' => (string) ($tenant->billing_interval ?? 'monthly'),
            'billing_overrides' => $tenant->billing_overrides,
            'enabled_modules' => $tenant->enabled_modules,
            'effective_enabled_modules' => $this->enabledModulesResolver->resolveForTenant($tenant),
            'operator_access_mode' => $tenant->operator_access_mode,
            'trial_ends_at' => $subscription['trial_ends_at'],
            'past_due_grace_ends_at' => $subscription['past_due_grace_ends_at'],
            'canceled_at' => $subscription['canceled_at'],
            'subscription_locked_at' => $subscription['subscription_locked_at'],
            'subscription' => $subscription,
            'theme_tokens' => $tenant->theme_tokens !== null
                ? TenantThemeTokensValidator::sanitizeForPublic($tenant->theme_tokens)
                : null,
            'warnings' => $downgradeWarnings,
            'payments' => app(StripeBillingConfig::class)->publicSnapshot(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function billingSnapshot(Tenant $tenant): array
    {
        return [
            'plan_tier' => (string) ($tenant->plan_tier ?? 'starter'),
            'subscription_status' => (string) ($tenant->subscription_status ?? 'active'),
            'seat_limit' => (int) ($tenant->seat_limit ?? 25),
            'billing_meter_starts_at' => $tenant->billing_meter_starts_at?->toIso8601String(),
            'billing_interval' => (string) ($tenant->billing_interval ?? 'monthly'),
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            'past_due_grace_ends_at' => $tenant->past_due_grace_ends_at?->toIso8601String(),
            'canceled_at' => $tenant->canceled_at?->toIso8601String(),
            'subscription_locked_at' => $tenant->subscription_locked_at?->toIso8601String(),
            'billing_overrides' => $tenant->billing_overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsSnapshot(Tenant $tenant): array
    {
        return [
            'mfa_required' => (bool) ($tenant->mfa_required ?? false),
            'theme_tokens' => $tenant->theme_tokens,
            'enabled_modules' => $tenant->enabled_modules,
            'operator_access_mode' => $tenant->operator_access_mode,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diffSnapshots(array $before, array $after): array
    {
        $changes = [];
        foreach ($before as $field => $from) {
            $to = $after[$field] ?? null;
            if ($from !== $to) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     * @param  list<string>  $fields
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function pickChanges(array $changes, array $fields): array
    {
        return array_intersect_key($changes, array_flip($fields));
    }
}
