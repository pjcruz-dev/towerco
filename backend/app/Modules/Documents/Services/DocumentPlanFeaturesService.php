<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Models\Tenant;
use App\Modules\Billing\Services\TenantPlanEntitlementsService;
use Illuminate\Validation\ValidationException;

final class DocumentPlanFeaturesService
{
    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
    ) {}

    /**
     * @return array{document_uploads: bool, max_documents_per_site: int|null}
     */
    public function features(): array
    {
        $tenantKey = tenant()?->getTenantKey();
        if ($tenantKey !== null) {
            /** @var Tenant|null $central */
            $central = Tenant::query()->find((string) $tenantKey);
            $modules = $central instanceof Tenant
                ? $this->entitlements->forTenant($central)['modules']
                : $this->entitlements->forTier('starter')['modules'];
        } else {
            $modules = $this->entitlements->forTier('starter')['modules'];
        }

        /** @var array<string, mixed> $documents */
        $documents = $modules['documents'] ?? [];

        $max = $documents['max_documents_per_site'] ?? null;

        return [
            'document_uploads' => (bool) ($documents['document_uploads'] ?? false),
            'max_documents_per_site' => is_numeric($max) ? (int) $max : null,
        ];
    }

    public function assertCanUpload(): void
    {
        if (! $this->features()['document_uploads']) {
            throw ValidationException::withMessages([
                'file' => [__('Document uploads require a Professional or Enterprise plan.')],
            ]);
        }
    }
}
