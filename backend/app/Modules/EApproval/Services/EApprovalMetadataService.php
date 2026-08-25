<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Schema;

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

        $departments = [];
        if (Schema::connection('tenant')->hasColumn('users', 'department')) {
            $departments = TenantUser::query()
                ->where('is_active', true)
                ->whereNotNull('department')
                ->where('department', '!=', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department')
                ->map(static fn (mixed $value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->values()
                ->all();
        }

        return [
            'roles' => $roles,
            'departments' => $departments,
            'emails' => $emails,
            'plan_features' => $this->planFeatures->snapshot(),
            'finance_procurement_policy' => $this->procurementPolicy->snapshot(),
        ];
    }
}
