<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * Per-tenant (per-environment) Coming Soon gate for public login surfaces.
 */
final class TenantComingSoonService
{
    public const DEFAULT_MESSAGE = 'This workspace is not open for sign-in yet. Check back soon, or contact your administrator.';

    /**
     * @return array{enabled: bool, message: string, contact: string|null}
     */
    public function publicStatus(?string $tenantId = null): array
    {
        $tenant = $this->resolveTenant($tenantId);
        if ($tenant === null) {
            return [
                'enabled' => false,
                'message' => self::DEFAULT_MESSAGE,
                'contact' => null,
            ];
        }

        return $this->statusFromTenant($tenant);
    }

    public function isEnabled(?string $tenantId = null): bool
    {
        return $this->publicStatus($tenantId)['enabled'];
    }

    public function assertSignInAllowed(?string $tenantId = null): void
    {
        $status = $this->publicStatus($tenantId);
        if (! $status['enabled']) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [$status['message']],
        ]);
    }

    /**
     * @return array{enabled: bool, message: string, contact: string|null}
     */
    public function statusFromTenant(Tenant $tenant): array
    {
        $message = trim((string) ($tenant->coming_soon_message ?? ''));
        $contact = trim((string) ($tenant->coming_soon_contact ?? ''));

        return [
            'enabled' => (bool) ($tenant->coming_soon_enabled ?? false),
            'message' => $message !== '' ? $message : self::DEFAULT_MESSAGE,
            'contact' => $contact !== '' ? $contact : null,
        ];
    }

    private function resolveTenant(?string $tenantId): ?Tenant
    {
        $id = $tenantId ?? (tenant('id') !== null ? (string) tenant('id') : null);
        if ($id === null || $id === '') {
            return null;
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($id);

        return $tenant;
    }
}
