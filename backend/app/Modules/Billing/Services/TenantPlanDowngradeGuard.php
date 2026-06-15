<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

final class TenantPlanDowngradeGuard
{
    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return list<string> warnings applied (empty if none or confirmed)
     */
    public function assertPlanTierChangeAllowed(Tenant $tenant, string $fromTier, array $data): array
    {
        if (! array_key_exists('plan_tier', $data)) {
            return [];
        }

        $toTier = $this->entitlements->normalizeTier((string) $data['plan_tier']);
        $from = $this->entitlements->normalizeTier($fromTier);

        if ($from === $toTier) {
            return [];
        }

        $warnings = $this->entitlements->downgradeWarnings($tenant, $from, $toTier);
        if ($warnings === []) {
            return [];
        }

        $confirmed = filter_var($data['confirm_plan_downgrade'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'plan_tier' => $warnings,
                'confirm_plan_downgrade' => [
                    __('Set confirm_plan_downgrade to true to apply this plan downgrade.'),
                ],
            ]);
        }

        return $warnings;
    }
}
