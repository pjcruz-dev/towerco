<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class TenantOffboardingService
{
    /**
     * Permanently remove a tenant: filesystem artifacts, central records, and tenant database.
     *
     * @param  array{confirmation?: string|null, cascade?: bool|null}  $input
     * @return array{
     *     tenant_id: string,
     *     domains_removed: list<string>,
     *     database_dropped: bool,
     *     filesystem_purged: bool,
     *     children_deleted: list<string>
     * }
     */
    public function deleteTenant(Tenant $tenant, array $input = []): array
    {
        $confirmation = trim((string) ($input['confirmation'] ?? ''));

        if ($confirmation !== $tenant->id) {
            throw ValidationException::withMessages([
                'confirmation' => [__('Type the tenant ID exactly to confirm permanent deletion.')],
            ]);
        }

        $cascade = (bool) ($input['cascade'] ?? false);
        $children = Tenant::query()->where('parent_tenant_id', $tenant->id)->get();

        if ($children->isNotEmpty() && ! $cascade) {
            $childSummary = $children
                ->map(fn (Tenant $child): string => sprintf(
                    '%s (%s)',
                    (string) ($child->environment ?? 'unknown'),
                    $child->domains()->first()?->domain ?? $child->id,
                ))
                ->values()
                ->all();

            throw ValidationException::withMessages([
                'tenant' => [
                    __(
                        'This tenant has linked environment tenants. Delete those first, or enable cascade delete. Linked: :tenants',
                        ['tenants' => implode(', ', $childSummary)],
                    ),
                ],
            ]);
        }

        $childrenDeleted = [];
        if ($cascade) {
            foreach ($children as $child) {
                /** @var Tenant $child */
                $childResult = $this->deleteTenant($child, [
                    'confirmation' => $child->id,
                    'cascade' => true,
                ]);
                $childrenDeleted = array_merge($childrenDeleted, [$childResult['tenant_id']], $childResult['children_deleted']);
            }
        }

        $tenant->loadMissing('domains');

        $tenantId = (string) $tenant->id;
        $domains = $tenant->domains->pluck('domain')->values()->all();

        $filesystemPurged = $this->purgeTenantFilesystem($tenantId);

        $tenant->delete();

        Log::info('platform.tenant.deleted', [
            'tenant_id' => $tenantId,
            'domains' => $domains,
            'filesystem_purged' => $filesystemPurged,
            'children_deleted' => $childrenDeleted,
            'actor_email' => auth()->user()?->email,
        ]);

        return [
            'tenant_id' => $tenantId,
            'domains_removed' => $domains,
            'database_dropped' => true,
            'filesystem_purged' => $filesystemPurged,
            'children_deleted' => $childrenDeleted,
        ];
    }

    private function purgeTenantFilesystem(string $tenantId): bool
    {
        $purged = false;

        $filesDisk = (string) config('toweros.tenant_files.disk', 'tenant_files');
        if (Storage::disk($filesDisk)->exists($tenantId)) {
            Storage::disk($filesDisk)->deleteDirectory($tenantId);
            $purged = true;
        }

        $tenantStoragePath = storage_path('app/tenant'.$tenantId);
        if (File::isDirectory($tenantStoragePath)) {
            File::deleteDirectory($tenantStoragePath);
            $purged = true;
        }

        $tenantPublicPath = storage_path('app/public/tenant'.$tenantId);
        if (File::isDirectory($tenantPublicPath)) {
            File::deleteDirectory($tenantPublicPath);
            $purged = true;
        }

        return $purged;
    }
}
