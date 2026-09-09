<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use App\Modules\Billing\Services\TenantPlanEntitlementsService;
use Illuminate\Validation\ValidationException;

final class DocExtractPlanFeaturesService
{
    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
    ) {}

    /**
     * @return array{plan_tier: string, enabled: bool}
     */
    public function snapshot(?string $tenantId = null): array
    {
        return $this->entitlements->docExtractFeatures($tenantId);
    }

    public function moduleEnabled(): bool
    {
        return $this->snapshot()['enabled'];
    }

    public function assertModuleEnabled(): void
    {
        if (! $this->moduleEnabled()) {
            throw ValidationException::withMessages([
                'doc_extract' => [__('DocExtract is not included on your current plan.')],
            ]);
        }
    }
}
