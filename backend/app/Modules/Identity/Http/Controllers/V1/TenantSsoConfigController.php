<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantSsoConfigController extends AbstractApiController
{
    public function show(): JsonResponse
    {
        $config = DB::table('tenant_sso_configs')
            ->where('tenant_id', (string) tenant('id'))
            ->where('provider', 'azure')
            ->first();

        if (! $config) {
            return $this->ok(null);
        }

        return $this->ok([
            'id' => $config->id,
            'provider' => $config->provider,
            'issuer' => $config->issuer,
            'client_id' => $config->client_id,
            'tenant_identifier' => $config->tenant_identifier,
            'group_mapping_rules' => json_decode((string) $config->group_mapping_rules, true),
            'auto_provision_users' => (bool) $config->auto_provision_users,
            'enabled' => (bool) $config->enabled,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'issuer' => ['nullable', 'string', 'max:255'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:2048'],
            'tenant_identifier' => ['nullable', 'string', 'max:255'],
            'group_mapping_rules' => ['nullable', 'array'],
            'auto_provision_users' => ['boolean'],
            'enabled' => ['boolean'],
        ]);

        $tenantId = (string) tenant('id');
        $existing = DB::table('tenant_sso_configs')
            ->where('tenant_id', $tenantId)
            ->where('provider', 'azure')
            ->first();

        $payload = [
            'tenant_id' => $tenantId,
            'provider' => 'azure',
            'issuer' => $data['issuer'] ?? null,
            'client_id' => $data['client_id'],
            'client_secret_encrypted' => encrypt($data['client_secret']),
            'tenant_identifier' => $data['tenant_identifier'] ?? 'common',
            'group_mapping_rules' => json_encode($data['group_mapping_rules'] ?? []),
            'auto_provision_users' => (bool) ($data['auto_provision_users'] ?? true),
            'enabled' => (bool) ($data['enabled'] ?? false),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('tenant_sso_configs')->where('id', $existing->id)->update($payload);
        } else {
            DB::table('tenant_sso_configs')->insert($payload + [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
            ]);
        }

        return $this->ok(['message' => __('SSO configuration updated.')]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => ['required', 'string'],
            'tenant_identifier' => ['required', 'string'],
        ]);

        return $this->ok([
            'ok' => true,
            'message' => __('Connection settings look valid.'),
        ]);
    }
}

