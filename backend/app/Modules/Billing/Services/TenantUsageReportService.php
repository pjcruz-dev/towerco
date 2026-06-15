<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\Tenant;
use App\Modules\AdminOne\Services\TenantSeatLimitService;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class TenantUsageReportService
{
    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
        private readonly TenantSeatLimitService $seats,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $tenantKey = (string) tenant('id');
        /** @var Tenant|null $central */
        $central = Tenant::query()->find($tenantKey);

        $periodStart = Carbon::now()->subDays(30)->startOfDay();

        $usage = $this->collectTenantUsage($periodStart);
        $tier = $this->entitlements->normalizeTier($central?->plan_tier);
        $entitlements = $central !== null
            ? $this->entitlements->forTenant($central)
            : $this->entitlements->forTier($tier);

        return [
            'tenant_id' => $tenantKey,
            'plan_tier' => $tier,
            'period_days' => 30,
            'period_start' => $periodStart->toIso8601String(),
            'seats' => [
                'used' => $usage['active_users'],
                'limit' => $central !== null
                    ? $this->entitlements->effectiveSeatLimit($central)
                    : $this->seats->seatLimit(),
                'total_users' => $usage['total_users'],
            ],
            'modules' => $usage['modules'],
            'entitlements' => $entitlements['modules'],
            'has_enterprise_overrides' => $central !== null && $this->entitlements->hasBillingOverrides($central),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectTenantUsage(Carbon $periodStart): array
    {
        return [
            'active_users' => TenantUser::query()->where('is_active', true)->count(),
            'total_users' => TenantUser::query()->count(),
            'modules' => [
                'e_approval' => $this->eApprovalUsage($periodStart),
                'project_one' => $this->projectOneUsage($periodStart),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function eApprovalUsage(Carbon $periodStart): array
    {
        if (! Schema::connection('tenant')->hasTable('e_approval_forms')) {
            return [
                'forms_total' => 0,
                'forms_published' => 0,
                'submissions_total' => 0,
                'submissions_last_30d' => 0,
            ];
        }

        $formsTotal = EApprovalForm::query()->count();
        $formsPublished = EApprovalForm::query()->where('status', 'published')->count();

        $submissionsTotal = 0;
        $submissionsLast30d = 0;
        if (Schema::connection('tenant')->hasTable('e_approval_submissions')) {
            $submissionsTotal = EApprovalSubmission::query()->count();
            $submissionsLast30d = EApprovalSubmission::query()
                ->where('created_at', '>=', $periodStart)
                ->count();
        }

        return [
            'forms_total' => $formsTotal,
            'forms_published' => $formsPublished,
            'submissions_total' => $submissionsTotal,
            'submissions_last_30d' => $submissionsLast30d,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function projectOneUsage(Carbon $periodStart): array
    {
        if (! Schema::connection('tenant')->hasTable('rollout_programs')) {
            return [
                'rollouts_total' => 0,
                'rollouts_last_30d' => 0,
            ];
        }

        return [
            'rollouts_total' => RolloutProgram::query()->count(),
            'rollouts_last_30d' => RolloutProgram::query()
                ->where('created_at', '>=', $periodStart)
                ->count(),
        ];
    }
}
