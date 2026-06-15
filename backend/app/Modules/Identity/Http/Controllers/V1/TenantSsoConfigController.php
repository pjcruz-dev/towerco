<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\TenantSsoConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantSsoConfigController extends AbstractApiController
{
    public function show(Request $request, TenantSsoConfigService $service): JsonResponse
    {
        $this->authorizeTenantSettings($request);

        $config = $service->findForTenant((string) tenant('id'), 'azure');

        return $this->ok($service->toAdminPayload($config));
    }

    public function update(Request $request, TenantSsoConfigService $service): JsonResponse
    {
        $this->authorizeTenantSettings($request);

        $data = $request->validate([
            'issuer' => ['nullable', 'string', 'max:255'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:2048'],
            'tenant_identifier' => ['nullable', 'string', 'max:255'],
            'group_mapping_rules' => ['nullable', 'array'],
            'allowed_email_domains' => ['nullable', 'array'],
            'allowed_email_domains.*' => ['string', 'max:253'],
            'auto_provision_users' => ['boolean'],
            'disable_password_login_when_enabled' => ['boolean'],
            'enabled' => ['boolean'],
        ]);

        $service->upsert((string) tenant('id'), $data);

        $config = $service->findForTenant((string) tenant('id'), 'azure');

        return $this->ok([
            'message' => __('Microsoft sign-in settings saved.'),
            'config' => $service->toAdminPayload($config),
        ]);
    }

    public function testConnection(Request $request, TenantSsoConfigService $service): JsonResponse
    {
        $this->authorizeTenantSettings($request);

        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'tenant_identifier' => ['required', 'string', 'max:255'],
        ]);

        $existing = $service->findForTenant((string) tenant('id'), 'azure');
        $hasSecret = ! empty($request->input('client_secret'))
            || ($existing !== null && trim((string) $existing->client_secret_encrypted) !== '');

        if (! $hasSecret) {
            return $this->ok([
                'ok' => false,
                'message' => __('Save a client secret before testing the connection.'),
            ]);
        }

        return $this->ok([
            'ok' => true,
            'message' => __('Application (client) ID and directory (tenant) ID are set. Register the redirect URI in Azure, then enable sign-in.'),
            'redirect_uri' => $service->redirectUri(),
            'client_id' => $data['client_id'],
            'tenant_identifier' => $data['tenant_identifier'],
        ]);
    }

    private function authorizeTenantSettings(Request $request): void
    {
        abort_unless($request->user()?->can('tenant:manage'), 403);
    }
}
