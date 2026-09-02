<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Models\TenantPlaybookBinding;
use Illuminate\Validation\ValidationException;

/**
 * Minimal policy-bundle service after Rollout module removal.
 */
final class RolloutPolicyBundleService
{
    public function __construct(
        private readonly RolloutPlaybookCatalogService $catalog,
    ) {}

    /**
     * @return list<RolloutPolicyBundle>
     */
    public function list(?string $status = null): array
    {
        $query = RolloutPolicyBundle::query()
            ->with('playbookVersion:id,version,name')
            ->orderByDesc('updated_at');

        if ($status !== null && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->get()->all();
    }

    public function find(string $id): RolloutPolicyBundle
    {
        /** @var RolloutPolicyBundle $bundle */
        $bundle = RolloutPolicyBundle::query()
            ->with('playbookVersion')
            ->findOrFail($id);

        return $bundle;
    }

    public function resolveDefaultForProvisioning(RolloutPlaybookVersion $playbookVersion): ?RolloutPolicyBundle
    {
        $code = config('toweros.tenant_provisioning.default_rollout_policy_code');
        if (is_string($code) && $code !== '') {
            /** @var RolloutPolicyBundle|null $byCode */
            $byCode = RolloutPolicyBundle::query()
                ->where('code', $code)
                ->where('status', RolloutPolicyBundle::STATUS_PUBLISHED)
                ->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        return RolloutPolicyBundle::query()
            ->where('playbook_version_id', $playbookVersion->id)
            ->where('status', RolloutPolicyBundle::STATUS_PUBLISHED)
            ->orderBy('created_at')
            ->first();
    }

    public function assignToTenant(
        Tenant $tenant,
        RolloutPolicyBundle $bundle,
        string $upgradePolicy = 'new_rollouts_only',
    ): TenantPlaybookBinding {
        if ($bundle->status !== RolloutPolicyBundle::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'rollout_policy_bundle_id' => [__('Only published policy bundles can be assigned.')],
            ]);
        }

        if ($bundle->playbook_version_id === null) {
            throw ValidationException::withMessages([
                'rollout_policy_bundle_id' => [__('Policy bundle is missing a playbook version.')],
            ]);
        }

        /** @var TenantPlaybookBinding $binding */
        $binding = TenantPlaybookBinding::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'playbook_version_id' => $bundle->playbook_version_id,
                'rollout_policy_bundle_id' => $bundle->id,
                'upgrade_policy' => $upgradePolicy,
                'assigned_at' => now(),
            ],
        );

        return $binding->fresh(['playbookVersion', 'rolloutPolicyBundle']);
    }

    public function ensureDefaultPublishedBundle(RolloutPlaybookVersion $playbookVersion): RolloutPolicyBundle
    {
        /** @var RolloutPolicyBundle $bundle */
        $bundle = RolloutPolicyBundle::query()->updateOrCreate(
            ['code' => 'default-retired'],
            [
                'name' => 'Default (retired)',
                'status' => RolloutPolicyBundle::STATUS_PUBLISHED,
                'playbook_version_id' => $playbookVersion->id,
                'timeline_templates' => [],
                'hidden_phases' => [],
                'gate_approval_policies' => [],
                'email_notification_policies' => [],
                'delivery_periods' => [],
                'changelog' => 'Stub bundle after Rollout module removal.',
                'published_at' => now(),
            ],
        );

        return $bundle;
    }

    public function ensureFullGateApprovalPublishedBundle(
        RolloutPlaybookVersion $playbookVersion,
        string $code = 'towerco-full-gate-approval',
        string $name = 'TowerCo Full Gate Approval',
    ): RolloutPolicyBundle {
        return $this->ensureDefaultPublishedBundle($playbookVersion);
    }
}
