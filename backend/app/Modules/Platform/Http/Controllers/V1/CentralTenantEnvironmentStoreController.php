<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantEnvironmentProvisioningService;
use App\Modules\Tenancy\Support\InitialAdminExposure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CentralTenantEnvironmentStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        TenantEnvironmentProvisioningService $environments,
    ): JsonResponse {
        $data = $request->validate([
            'environment' => ['required', 'string', 'in:local,test,staging,production'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'migrate' => ['sometimes', 'boolean'],
            'seed' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $environments->createFromTenant($tenant, [
                'environment' => $data['environment'],
                'domain' => $data['domain'] ?? null,
                'migrate' => (bool) ($data['migrate'] ?? true),
                'seed' => (bool) ($data['seed'] ?? false),
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'domain' => [$e->getMessage()],
            ]);
        }

        $created = $result['tenant'];
        $payload = [
            'tenant_id' => $created->id,
            'source_tenant_id' => $result['source_tenant_id'],
            'org_root_tenant_id' => $result['org_root_tenant_id'],
            'domain' => $created->domains()->first()?->domain,
            'slug' => $created->slug,
            'brand_domain' => $created->brand_domain,
            'environment' => $created->environment,
            'parent_tenant_id' => $created->parent_tenant_id,
            'playbook_version' => $result['playbook_version'] ?? null,
            'assigned_policy_code' => $result['assigned_policy_code'] ?? null,
            'domain_endpoints' => $result['domain_endpoints']['endpoints'] ?? null,
            'public_holidays_seeded' => $result['public_holidays_seeded'] ?? 0,
            'holiday_years' => $result['holiday_years'] ?? [],
        ];

        if (isset($result['initial_admin'])) {
            $payload['initial_admin'] = InitialAdminExposure::forTransport($result['initial_admin']);
        }

        return $this->ok($payload, 201);
    }
}
