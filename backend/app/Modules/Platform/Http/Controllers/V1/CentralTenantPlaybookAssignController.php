<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Services\RolloutPlaybookCatalogService;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use App\Modules\Rollout\Services\TenantPlaybookSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CentralTenantPlaybookAssignController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        RolloutPlaybookCatalogService $catalog,
        RolloutPolicyBundleService $policyBundles,
        TenantPlaybookSyncService $sync,
    ): JsonResponse {
        $data = $request->validate([
            'rollout_policy_bundle_id' => ['sometimes', 'nullable', 'uuid'],
            'playbook_version_id' => ['sometimes', 'nullable', 'uuid'],
            'upgrade_policy' => ['sometimes', 'string', 'in:new_rollouts_only,include_draft_rollouts'],
            'sync_tenant_database' => ['sometimes', 'boolean'],
        ]);

        $bundleId = $data['rollout_policy_bundle_id'] ?? null;
        $versionId = $data['playbook_version_id'] ?? null;

        if ($bundleId === null && $versionId === null) {
            throw ValidationException::withMessages([
                'rollout_policy_bundle_id' => [__('A rollout policy bundle or playbook version is required.')],
            ]);
        }

        if ($bundleId !== null) {
            /** @var RolloutPolicyBundle $bundle */
            $bundle = RolloutPolicyBundle::query()->findOrFail($bundleId);
            $binding = $policyBundles->assignToTenant(
                $tenant,
                $bundle,
                $data['upgrade_policy'] ?? 'new_rollouts_only',
            );
            $assignedVersion = $bundle->playbookVersion?->version;
            $assignedPolicy = $bundle->code;
        } else {
            /** @var RolloutPlaybookVersion $version */
            $version = RolloutPlaybookVersion::query()->findOrFail((string) $versionId);
            $binding = $catalog->assignToTenant(
                $tenant,
                $version,
                $data['upgrade_policy'] ?? 'new_rollouts_only',
            );
            $binding->update(['rollout_policy_bundle_id' => null]);
            $assignedVersion = $version->version;
            $assignedPolicy = null;
        }

        if ($data['sync_tenant_database'] ?? true) {
            $sync->syncBindingToTenantDatabase($tenant, $binding->fresh(['playbookVersion', 'rolloutPolicyBundle']), $request->user()?->email);
        }

        return $this->ok([
            'tenant_id' => $tenant->id,
            'assigned_version' => $assignedVersion,
            'assigned_policy_code' => $assignedPolicy,
            'rollout_policy_bundle_id' => $binding->rollout_policy_bundle_id,
            'upgrade_policy' => $binding->upgrade_policy,
            'assigned_at' => $binding->assigned_at?->toIso8601String(),
        ]);
    }
}
