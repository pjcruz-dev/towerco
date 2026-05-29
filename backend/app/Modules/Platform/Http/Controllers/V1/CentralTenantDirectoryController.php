<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Support\TenantThemeTokensValidator;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\TenantPlaybookBinding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CentralTenantDirectoryController extends AbstractApiController
{
    public function index(Request $request): JsonResponse
    {
        $search = Str::limit(trim((string) $request->query('search', '')), 255, '');

        $query = Tenant::query()
            ->with(['domains:id,domain,tenant_id'])
            ->orderByDesc('created_at');

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like): void {
                $q->where('id', 'like', $like)
                    ->orWhereHas('domains', function ($domains) use ($like): void {
                        $domains->where('domain', 'like', $like);
                    });
            });
        }

        $rows = $query->get()->map(static function (Tenant $tenant): array {
            return [
                'id' => $tenant->id,
                'domains' => $tenant->domains->pluck('domain')->values()->all(),
                'created_at' => $tenant->created_at?->toIso8601String(),
                'mfa_required' => (bool) ($tenant->mfa_required ?? true),
                'plan_tier' => (string) ($tenant->plan_tier ?? 'starter'),
                'subscription_status' => (string) ($tenant->subscription_status ?? 'active'),
                'seat_limit' => (int) ($tenant->seat_limit ?? 25),
                'slug' => $tenant->slug,
                'brand_domain' => $tenant->brand_domain,
                'environment' => (string) ($tenant->environment ?? 'production'),
                'parent_tenant_id' => $tenant->parent_tenant_id,
                'theme_tokens' => $tenant->theme_tokens !== null
                    ? TenantThemeTokensValidator::sanitizeForPublic($tenant->theme_tokens)
                    : null,
            ];
        });

        $tenantIds = $rows->pluck('id')->all();
        $bindings = TenantPlaybookBinding::query()
            ->whereIn('tenant_id', $tenantIds)
            ->with(['playbookVersion:id,version', 'rolloutPolicyBundle:id,code,name'])
            ->get()
            ->keyBy('tenant_id');

        $latestVersion = RolloutPlaybookVersion::query()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->value('version');

        $rows = $rows->map(static function (array $row) use ($bindings, $latestVersion): array {
            /** @var TenantPlaybookBinding|null $binding */
            $binding = $bindings->get($row['id']);
            $assigned = $binding?->playbookVersion?->version;

            $row['assigned_playbook_version'] = $assigned;
            $row['assigned_rollout_policy_code'] = $binding?->rolloutPolicyBundle?->code;
            $row['assigned_rollout_policy_name'] = $binding?->rolloutPolicyBundle?->name;
            $row['rollout_policy_bundle_id'] = $binding?->rollout_policy_bundle_id;
            $row['playbook_upgrade_available'] = $latestVersion !== null
                && $assigned !== null
                && version_compare((string) $latestVersion, (string) $assigned, '>');

            return $row;
        });

        return $this->ok($rows);
    }
}
