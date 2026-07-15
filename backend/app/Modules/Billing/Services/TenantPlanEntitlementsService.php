<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\Tenant;
use App\Modules\EApproval\Models\EApprovalForm;
use Illuminate\Support\Facades\Schema;

final class TenantPlanEntitlementsService
{
    private const VALID_TIERS = ['starter', 'professional', 'enterprise'];

    public function __construct(
        private readonly PlatformBillingCatalogService $catalog,
    ) {}

    /**
     * @return list<string>
     */
    public function validTiers(): array
    {
        return self::VALID_TIERS;
    }

    public function normalizeTier(?string $tier): string
    {
        $normalized = strtolower(trim((string) $tier));

        return in_array($normalized, self::VALID_TIERS, true) ? $normalized : 'starter';
    }

    public function resolvePlanTierForTenantId(?string $tenantId): string
    {
        if ($tenantId === null || $tenantId === '') {
            return 'starter';
        }

        /** @var Tenant|null $central */
        $central = Tenant::query()->find($tenantId);

        return $this->normalizeTier($central?->plan_tier);
    }

    public function resolvePlanTierForCurrentTenant(): string
    {
        return $this->resolvePlanTierForTenantId(tenant()?->getTenantKey());
    }

    public function tierRank(string $tier): int
    {
        $config = $this->tierConfig($this->normalizeTier($tier));

        return (int) ($config['sort'] ?? 0);
    }

    public function isDowngrade(string $fromTier, string $toTier): bool
    {
        return $this->tierRank($toTier) < $this->tierRank($fromTier);
    }

    /**
     * @return array{
     *   plan_tier: string,
     *   label: string,
     *   modules: array<string, array<string, mixed>>
     * }
     */
    public function forTier(string $tier): array
    {
        $normalized = $this->normalizeTier($tier);
        $config = $this->tierConfig($normalized);
        /** @var array<string, array<string, mixed>> $modules */
        $modules = $config['modules'] ?? [];

        return [
            'plan_tier' => $normalized,
            'label' => (string) ($config['label'] ?? ucfirst($normalized)),
            'modules' => $modules,
        ];
    }

    /**
     * Catalog tier merged with per-tenant enterprise overrides.
     *
     * @return array{
     *   plan_tier: string,
     *   label: string,
     *   modules: array<string, array<string, mixed>>
     * }
     */
    public function forTenant(Tenant $tenant): array
    {
        $base = $this->forTier($this->normalizeTier($tenant->plan_tier));
        $overrides = $tenant->billing_overrides;
        if (! is_array($overrides) || $overrides === []) {
            return $base;
        }

        return $this->mergeOverrides($base, $overrides);
    }

    public function hasBillingOverrides(Tenant $tenant): bool
    {
        $overrides = $tenant->billing_overrides;

        return is_array($overrides) && $overrides !== [];
    }

    public function effectiveSeatLimit(Tenant $tenant): int
    {
        $overrides = $tenant->billing_overrides;
        if (is_array($overrides)) {
            if (isset($overrides['seat_limit'])) {
                return max(1, (int) $overrides['seat_limit']);
            }
            if (isset($overrides['included_paid_seats'])) {
                return max(1, (int) $overrides['included_paid_seats']);
            }
        }

        if ($tenant->seat_limit !== null && (int) $tenant->seat_limit > 0) {
            return max(1, (int) $tenant->seat_limit);
        }

        $included = $this->catalog->tierIncluded($this->normalizeTier($tenant->plan_tier), 'paid_seats');

        return max(1, $included > 0 ? $included : 25);
    }

    public function effectiveRfiLimit(Tenant $tenant): int
    {
        $overrides = is_array($tenant->billing_overrides) ? $tenant->billing_overrides : [];
        $tier = $this->normalizeTier($tenant->plan_tier);

        $base = isset($overrides['included_rfi_units'])
            ? max(0, (int) $overrides['included_rfi_units'])
            : $this->catalog->tierIncluded($tier, 'rfi_units');

        $grandfather = isset($overrides['grandfather_rfi_units'])
            ? max(0, (int) $overrides['grandfather_rfi_units'])
            : 0;

        return max(0, $base + $grandfather);
    }

    public function effectiveAnnualDiscountPercent(Tenant $tenant): float
    {
        $overrides = is_array($tenant->billing_overrides) ? $tenant->billing_overrides : [];
        $tenantOverride = isset($overrides['annual_discount_percent'])
            ? (float) $overrides['annual_discount_percent']
            : null;

        return $this->catalog->effectiveAnnualDiscountPercent(
            $this->normalizeTier($tenant->plan_tier),
            $tenantOverride,
        );
    }

    /**
     * E-Approval slice (backward compatible with legacy plan_features shape).
     *
     * @return array{plan_tier: string, file_uploads: bool, max_file_fields: int|null}
     */
    public function eApprovalFeatures(?string $tenantId = null): array
    {
        if ($tenantId !== null) {
            /** @var Tenant|null $central */
            $central = Tenant::query()->find($tenantId);
            if ($central instanceof Tenant) {
                $tier = $this->normalizeTier($central->plan_tier);
                $modules = $this->forTenant($central)['modules'];
            } else {
                $tier = 'starter';
                $modules = $this->forTier($tier)['modules'];
            }
        } else {
            $tenantKey = tenant()?->getTenantKey();
            if ($tenantKey !== null) {
                /** @var Tenant|null $central */
                $central = Tenant::query()->find((string) $tenantKey);
                if ($central instanceof Tenant) {
                    $tier = $this->normalizeTier($central->plan_tier);
                    $modules = $this->forTenant($central)['modules'];
                } else {
                    $tier = $this->resolvePlanTierForCurrentTenant();
                    $modules = $this->forTier($tier)['modules'];
                }
            } else {
                $tier = $this->resolvePlanTierForCurrentTenant();
                $modules = $this->forTier($tier)['modules'];
            }
        }
        /** @var array<string, mixed> $eApproval */
        $eApproval = $modules['e_approval'] ?? [];

        $maxFileFields = $this->normalizeNullableIntLimit($eApproval, 'max_file_fields');

        return [
            'plan_tier' => $tier,
            'file_uploads' => (bool) ($eApproval['file_uploads'] ?? false),
            'max_file_fields' => $maxFileFields,
        ];
    }

    /**
     * Ticketing slice for plan gating and UI.
     *
     * @return array{plan_tier: string, enabled: bool, file_uploads: bool, max_attachments_per_ticket: int|null}
     */
    public function ticketingFeatures(?string $tenantId = null): array
    {
        if ($tenantId !== null) {
            /** @var Tenant|null $central */
            $central = Tenant::query()->find($tenantId);
            if ($central instanceof Tenant) {
                $tier = $this->normalizeTier($central->plan_tier);
                $modules = $this->forTenant($central)['modules'];
            } else {
                $tier = 'starter';
                $modules = $this->forTier($tier)['modules'];
            }
        } else {
            $tenantKey = tenant()?->getTenantKey();
            if ($tenantKey !== null) {
                /** @var Tenant|null $central */
                $central = Tenant::query()->find((string) $tenantKey);
                if ($central instanceof Tenant) {
                    $tier = $this->normalizeTier($central->plan_tier);
                    $modules = $this->forTenant($central)['modules'];
                } else {
                    $tier = $this->resolvePlanTierForCurrentTenant();
                    $modules = $this->forTier($tier)['modules'];
                }
            } else {
                $tier = $this->resolvePlanTierForCurrentTenant();
                $modules = $this->forTier($tier)['modules'];
            }
        }

        /** @var array<string, mixed> $ticketing */
        $ticketing = $modules['ticketing'] ?? [];

        $maxAttachments = $this->normalizeNullableIntLimit($ticketing, 'max_attachments_per_ticket');

        return [
            'plan_tier' => $tier,
            'enabled' => (bool) ($ticketing['enabled'] ?? false),
            'file_uploads' => (bool) ($ticketing['file_uploads'] ?? false),
            'max_attachments_per_ticket' => $maxAttachments,
        ];
    }

    /**
     * Procurement-One slice for plan gating and UI.
     *
     * @return array{
     *   plan_tier: string,
     *   enabled: bool,
     *   goods_receipt: bool,
     *   advanced_numbering: bool,
     *   inventory: bool
     * }
     */
    public function procurementOneFeatures(?string $tenantId = null): array
    {
        if ($tenantId !== null) {
            /** @var Tenant|null $central */
            $central = Tenant::query()->find($tenantId);
            if ($central instanceof Tenant) {
                $tier = $this->normalizeTier($central->plan_tier);
                $modules = $this->forTenant($central)['modules'];
            } else {
                $tier = 'starter';
                $modules = $this->forTier($tier)['modules'];
            }
        } else {
            $tenantKey = tenant()?->getTenantKey();
            if ($tenantKey !== null) {
                /** @var Tenant|null $central */
                $central = Tenant::query()->find((string) $tenantKey);
                if ($central instanceof Tenant) {
                    $tier = $this->normalizeTier($central->plan_tier);
                    $modules = $this->forTenant($central)['modules'];
                } else {
                    $tier = $this->resolvePlanTierForCurrentTenant();
                    $modules = $this->forTier($tier)['modules'];
                }
            } else {
                $tier = $this->resolvePlanTierForCurrentTenant();
                $modules = $this->forTier($tier)['modules'];
            }
        }

        /** @var array<string, mixed> $procurementOne */
        $procurementOne = $modules['procurement_one'] ?? [];

        return [
            'plan_tier' => $tier,
            'enabled' => (bool) ($procurementOne['enabled'] ?? false),
            'goods_receipt' => (bool) ($procurementOne['goods_receipt'] ?? false),
            'advanced_numbering' => (bool) ($procurementOne['advanced_numbering'] ?? false),
            'inventory' => (bool) ($procurementOne['inventory'] ?? false),
            'ap_invoices' => (bool) ($procurementOne['ap_invoices'] ?? false),
            'payment_tracking' => (bool) ($procurementOne['payment_tracking'] ?? false),
            'rfq_sourcing' => (bool) ($procurementOne['rfq_sourcing'] ?? false),
            'vendor_contracts' => (bool) ($procurementOne['vendor_contracts'] ?? false),
            'reporting_exports' => (bool) ($procurementOne['reporting_exports'] ?? false),
        ];
    }

    /**
     * Full catalog for plan comparison UI.
     *
     * @return array{
     *   tiers: list<array{
     *     plan_tier: string,
     *     label: string,
     *     sort: int,
     *     modules: array<string, array<string, mixed>>
     *   }>
     * }
     */
    public function catalog(): array
    {
        return $this->catalog->resolvedCatalog();
    }

    /**
     * @return list<string>
     */
    public function downgradeWarnings(Tenant $tenant, string $fromTier, string $toTier): array
    {
        if (! $this->isDowngrade($fromTier, $toTier)) {
            return [];
        }

        $warnings = [];
        $from = $this->forTier($fromTier);
        $to = $this->forTier($toTier);

        $fromEa = $from['modules']['e_approval'] ?? [];
        $toEa = $to['modules']['e_approval'] ?? [];

        if (($fromEa['file_uploads'] ?? false) && ! ($toEa['file_uploads'] ?? false)) {
            $counts = $this->countEApprovalFileFields($tenant);
            if ($counts['published'] > 0) {
                $warnings[] = __(
                    ':count published form(s) include file upload fields; they will fail validation for new submissions on :tier until file fields are removed or the plan is upgraded.',
                    ['count' => $counts['published'], 'tier' => $to['label']],
                );
            }
            if ($counts['draft'] > 0) {
                $warnings[] = __(
                    ':count draft form(s) include file upload fields; publish will be blocked on :tier until those fields are removed.',
                    ['count' => $counts['draft'], 'tier' => $to['label']],
                );
            }
        } elseif (($fromEa['max_file_fields'] ?? null) !== ($toEa['max_file_fields'] ?? null)) {
            $toMax = $toEa['max_file_fields'] ?? null;
            if ($toMax !== null) {
                $exceeding = $this->formsExceedingFileFieldLimit($tenant, (int) $toMax);
                if ($exceeding > 0) {
                    $warnings[] = __(
                        ':count form(s) have more than :max file field(s); reduce file fields before downgrading to :tier.',
                        ['count' => $exceeding, 'max' => $toMax, 'tier' => $to['label']],
                    );
                }
            }
        }

        return $warnings;
    }

    /**
     * @return array{published: int, draft: int}
     */
    private function countEApprovalFileFields(Tenant $tenant): array
    {
        return $tenant->run(function (): array {
            if (! Schema::connection('tenant')->hasTable('e_approval_form_fields')) {
                return ['published' => 0, 'draft' => 0];
            }

            $published = EApprovalForm::query()
                ->where('status', 'published')
                ->whereHas('fields', static fn ($q) => $q->where('type', 'file'))
                ->count();

            $draft = EApprovalForm::query()
                ->where('status', 'draft')
                ->whereHas('fields', static fn ($q) => $q->where('type', 'file'))
                ->count();

            return ['published' => $published, 'draft' => $draft];
        });
    }

    private function formsExceedingFileFieldLimit(Tenant $tenant, int $max): int
    {
        return $tenant->run(function () use ($max): int {
            if (! Schema::connection('tenant')->hasTable('e_approval_form_fields')) {
                return 0;
            }

            return (int) EApprovalForm::query()
                ->withCount([
                    'fields as file_field_count' => static fn ($q) => $q->where('type', 'file'),
                ])
                ->get()
                ->filter(static fn (EApprovalForm $form): bool => (int) ($form->file_field_count ?? 0) > $max)
                ->count();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function tierConfig(string $tier): array
    {
        /** @var array<string, array<string, mixed>> $tiers */
        $tiers = config('billing.plan_tiers', []);

        return $tiers[$tier] ?? $tiers['starter'] ?? [];
    }

    /**
     * Null in catalog means unlimited; missing key defaults to 0 (disabled).
     *
     * @param  array<string, mixed>  $config
     */
    private function normalizeNullableIntLimit(array $config, string $key): ?int
    {
        if (! array_key_exists($key, $config)) {
            return 0;
        }

        $value = $config[$key];
        if ($value === null) {
            return null;
        }

        return max(0, (int) $value);
    }

    /**
     * @param  array{plan_tier: string, label: string, modules: array<string, array<string, mixed>>}  $base
     * @param  array<string, mixed>  $overrides
     * @return array{plan_tier: string, label: string, modules: array<string, array<string, mixed>>}
     */
    private function mergeOverrides(array $base, array $overrides): array
    {
        $modules = $base['modules'];
        /** @var array<string, array<string, mixed>> $overrideModules */
        $overrideModules = $overrides['modules'] ?? [];

        foreach ($overrideModules as $moduleKey => $patch) {
            if (! is_array($patch)) {
                continue;
            }
            $existing = $modules[$moduleKey] ?? [];
            $modules[$moduleKey] = array_merge($existing, $patch);
        }

        return [
            ...$base,
            'modules' => $modules,
        ];
    }
}
