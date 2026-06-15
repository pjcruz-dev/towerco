<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\User;
use App\Modules\Platform\Services\PlatformTenantAuditLogger;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
use App\Modules\Tenancy\Services\TenantOnboardingService;
use App\Modules\Tenancy\Support\InitialAdminExposure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CentralTenantProvisioningController extends AbstractApiController
{
    public function store(
        Request $request,
        TenantOnboardingService $onboarding,
        PlatformTenantAuditLogger $audit,
    ): JsonResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'uuid'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:64'],
            'brand_domain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'environment' => ['sometimes', 'string', 'in:local,test,staging,production'],
            'tco_sequence_prefix' => ['sometimes', 'nullable', 'string', 'max:8'],
            'playbook_version_id' => ['sometimes', 'nullable', 'uuid'],
            'rollout_policy_bundle_id' => ['sometimes', 'nullable', 'uuid'],
            'migrate' => ['sometimes', 'boolean'],
            'seed' => ['sometimes', 'boolean'],
        ]);

        try {
            $result = $onboarding->createTenant([
                'tenant_id' => $data['tenant_id'] ?? null,
                'domain' => $data['domain'],
                'slug' => $data['slug'] ?? null,
                'brand_domain' => $data['brand_domain'] ?? null,
                'environment' => $data['environment'] ?? 'local',
                'tco_sequence_prefix' => $data['tco_sequence_prefix'] ?? null,
                'playbook_version_id' => $data['playbook_version_id'] ?? null,
                'migrate' => (bool) ($data['migrate'] ?? true),
                'seed' => (bool) ($data['seed'] ?? false),
            ]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'domain' => [$e->getMessage()],
            ]);
        }

        $tenant = $result['tenant'];
        $payload = [
            'tenant_id' => $tenant->id,
            'domain' => $tenant->domains()->first()?->domain,
            'slug' => $tenant->slug,
            'brand_domain' => $tenant->brand_domain,
            'environment' => $tenant->environment,
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
            PlatformTenantAuditEventType::TENANT_PROVISIONED,
            $tenant,
            $actor,
            null,
            [
                'domain' => $payload['domain'],
                'slug' => $payload['slug'],
                'environment' => $payload['environment'],
                'playbook_version' => $payload['playbook_version'],
            ],
        );

        return $this->ok($payload, 201);
    }
}
