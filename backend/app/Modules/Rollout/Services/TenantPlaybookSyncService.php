<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Models\TenantPlaybookBinding;
use App\Modules\Platform\Services\RolloutPlaybookCatalogService;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use App\Modules\Rollout\Data\RolloutEmailNotificationPolicyDefaults;
use App\Modules\Rollout\Data\RolloutGateApprovalPolicyDefaults;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;

final class TenantPlaybookSyncService
{
    public function __construct(
        private readonly RolloutPlaybookCatalogService $catalog,
        private readonly RolloutPolicyBundleService $policyBundles,
    ) {}

    public function syncBindingToTenantDatabase(Tenant $tenant, TenantPlaybookBinding $binding, ?string $actorEmail = null): void
    {
        $version = $binding->playbookVersion;
        if ($version === null) {
            return;
        }

        $binding->loadMissing('rolloutPolicyBundle');
        $bundle = $binding->rolloutPolicyBundle;
        $latest = $this->catalog->latestPublished();

        $tenant->run(function () use ($tenant, $version, $latest, $binding, $bundle, $actorEmail): void {
            $existing = TenantRolloutPlaybookConfig::query()->first();
            $previousVersion = $existing?->assigned_version;

            $snapshot = $bundle !== null
                ? $this->policyBundles->buildTenantPlaybookSnapshot($bundle, $version)
                : $this->catalog->snapshot($version);

            $payload = [
                'assigned_version' => $version->version,
                'latest_platform_version' => $latest?->version,
                'playbook_snapshot' => $snapshot,
                'assigned_at' => now(),
            ];

            $payload['gate_approval_policies'] = $bundle !== null
                ? ($bundle->gate_approval_policies ?? RolloutGateApprovalPolicyDefaults::all())
                : RolloutGateApprovalPolicyDefaults::all();
            $payload['email_notification_policies'] = $bundle !== null
                ? ($bundle->email_notification_policies ?? RolloutEmailNotificationPolicyDefaults::all())
                : RolloutEmailNotificationPolicyDefaults::all();

            if ($existing !== null) {
                $existing->update($payload);
            } else {
                TenantRolloutPlaybookConfig::query()->create($payload);
            }

            \Illuminate\Support\Facades\Log::info('platform.playbook.synced', [
                'tenant_id' => $tenant->id,
                'previous_version' => $previousVersion,
                'assigned_version' => $version->version,
                'policy_bundle_code' => $bundle?->code,
                'upgrade_policy' => $binding->upgrade_policy,
                'actor_email' => $actorEmail,
            ]);
        });
    }

    public function refreshLatestPlatformVersion(Tenant $tenant): void
    {
        $latest = $this->catalog->latestPublished();
        if ($latest === null) {
            return;
        }

        $tenant->run(function () use ($latest): void {
            $config = TenantRolloutPlaybookConfig::query()->first();
            if ($config === null) {
                return;
            }

            if ($config->assigned_version === $latest->version) {
                $config->latest_platform_version = $latest->version;
                $config->save();

                return;
            }

            $config->latest_platform_version = $latest->version;
            $config->save();
        });
    }

    public function propagateLatestVersionToAllTenants(): void
    {
        $latest = $this->catalog->latestPublished();
        if ($latest === null) {
            return;
        }

        Tenant::query()->pluck('id')->each(function (string $tenantId) use ($latest): void {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                return;
            }

            $this->refreshLatestPlatformVersion($tenant);
        });
    }
}
