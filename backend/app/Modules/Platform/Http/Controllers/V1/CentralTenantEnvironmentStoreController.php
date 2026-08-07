<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Platform\Services\PlatformTenantAuditLogger;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
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
        PlatformTenantAuditLogger $audit,
    ): JsonResponse {
        $data = $request->validate([
            'environment' => ['required', 'string', 'in:local,test,staging,production'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'migrate' => ['sometimes', 'boolean'],
            'seed' => ['sometimes', 'boolean'],
            'enabled_modules' => ['sometimes', 'nullable', 'array'],
            'enabled_modules.*' => ['string', 'max:64'],
            'admin_password' => ['sometimes', 'nullable', 'string', 'min:12', 'max:128'],
        ]);

        try {
            $payload = [
                'environment' => $data['environment'],
                'domain' => $data['domain'] ?? null,
                'migrate' => (bool) ($data['migrate'] ?? true),
                'seed' => (bool) ($data['seed'] ?? false),
            ];
            if (array_key_exists('enabled_modules', $data)) {
                $payload['enabled_modules'] = $data['enabled_modules'];
            }
            if (! empty($data['admin_password'])) {
                $payload['admin_password'] = $data['admin_password'];
            }

            $result = $environments->createFromTenant($tenant, $payload);
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

        /** @var User|null $actor */
        $actor = $request->user();
        $audit->log(
            PlatformTenantAuditEventType::TENANT_ENVIRONMENT_PROVISIONED,
            $created,
            $actor,
            null,
            [
                'source_tenant_id' => $payload['source_tenant_id'],
                'domain' => $payload['domain'],
                'environment' => $payload['environment'],
            ],
        );

        return $this->ok($payload, 201);
    }
}
