<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Identity\Models\TenantUser;

final class EApprovalMetadataService
{
    public function __construct(
        private readonly EApprovalPlanFeaturesService $planFeatures,
        private readonly EApprovalFinanceProcurementPolicyService $procurementPolicy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $roles = TenantRole::query()
            ->where('guard_name', 'sanctum')
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $emails = TenantUser::query()
            ->where('is_active', true)
            ->orderBy('email')
            ->pluck('email')
            ->values()
            ->all();

        return [
            'roles' => $roles,
            'departments' => [],
            'emails' => $emails,
            'plan_features' => $this->planFeatures->snapshot(),
            'finance_procurement_policy' => $this->procurementPolicy->snapshot(),
        ];
    }
}
