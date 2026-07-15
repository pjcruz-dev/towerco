<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use Illuminate\Validation\ValidationException;

final class ProcurementPrBudgetPolicyService
{
    public const SETTINGS_KEY = 'pr_budget_policy';

    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
        private readonly ProcurementBudgetUtilizationService $budgetUtilization,
    ) {}

    /**
     * @return array{enabled: bool, mode: string}
     */
    public function policy(): array
    {
        $raw = $this->settings->getJson(self::SETTINGS_KEY);
        $enabled = (bool) ($raw['enabled'] ?? false);
        $mode = (string) ($raw['mode'] ?? 'warn');
        if (! in_array($mode, ['warn', 'block'], true)) {
            $mode = 'warn';
        }

        return ['enabled' => $enabled, 'mode' => $mode];
    }

    public function isEnforced(): bool
    {
        return $this->policy()['enabled'] === true;
    }

    public function blocksOverBudget(): bool
    {
        $policy = $this->policy();

        return $policy['enabled'] && $policy['mode'] === 'block';
    }

    /**
     * @param  array{enabled?: bool, mode?: string}  $input
     * @return array{enabled: bool, mode: string}
     */
    public function validateAndNormalize(array $input): array
    {
        $enabled = (bool) ($input['enabled'] ?? false);
        $mode = (string) ($input['mode'] ?? 'warn');

        return [
            'enabled' => $enabled,
            'mode' => in_array($mode, ['warn', 'block'], true) ? $mode : 'warn',
        ];
    }

    /**
     * @return array{
     *     blocked: bool,
     *     warning: string|null,
     *     budget_total: float|null,
     *     committed: float|null,
     *     available: float|null
     * }
     */
    public function evaluate(ProcurementPr $pr): array
    {
        if (! $this->isEnforced()) {
            return $this->emptyEvaluation();
        }

        $budgetTotal = $this->resolveBudgetTotal($pr);
        if ($budgetTotal === null) {
            return $this->emptyEvaluation();
        }

        $committed = $this->sumCommittedForScope($pr);
        $available = max(0, round($budgetTotal - $committed, 2));
        $amount = (float) $pr->estimated_total;

        if ($amount <= $available + 0.0001) {
            return [
                'blocked' => false,
                'warning' => null,
                'budget_total' => $budgetTotal,
                'committed' => $committed,
                'available' => $available,
            ];
        }

        $message = __(
            'PR total :amount exceeds available project/rollout budget of :available (budget :budget, committed :committed).',
            [
                'amount' => number_format($amount, 2, '.', ''),
                'available' => number_format($available, 2, '.', ''),
                'budget' => number_format($budgetTotal, 2, '.', ''),
                'committed' => number_format($committed, 2, '.', ''),
            ],
        );

        if ($this->blocksOverBudget()) {
            throw ValidationException::withMessages([
                'estimated_total' => [$message],
            ]);
        }

        return [
            'blocked' => false,
            'warning' => $message,
            'budget_total' => $budgetTotal,
            'committed' => $committed,
            'available' => $available,
        ];
    }

    /**
     * @return array{
     *     blocked: bool,
     *     warning: string|null,
     *     budget_total: float|null,
     *     committed: float|null,
     *     available: float|null
     * }
     */
    public function preview(ProcurementPr $pr): array
    {
        if (! $this->isEnforced()) {
            return $this->emptyEvaluation();
        }

        $budgetTotal = $this->resolveBudgetTotal($pr);
        if ($budgetTotal === null) {
            return $this->emptyEvaluation();
        }

        $committed = $this->sumCommittedForScope($pr);
        $available = max(0, round($budgetTotal - $committed, 2));
        $amount = (float) $pr->estimated_total;
        $warning = null;

        if ($amount > $available + 0.0001) {
            $warning = __(
                'PR total :amount exceeds available project/rollout budget of :available.',
                [
                    'amount' => number_format($amount, 2, '.', ''),
                    'available' => number_format($available, 2, '.', ''),
                ],
            );
        }

        return [
            'blocked' => $amount > $available + 0.0001 && $this->blocksOverBudget(),
            'warning' => $warning,
            'budget_total' => $budgetTotal,
            'committed' => $committed,
            'available' => $available,
        ];
    }

    /**
     * @return array{blocked: bool, warning: string|null, budget_total: float|null, committed: float|null, available: float|null}
     */
    public function emptyEvaluation(): array
    {
        return [
            'blocked' => false,
            'warning' => null,
            'budget_total' => null,
            'committed' => null,
            'available' => null,
        ];
    }

    private function resolveBudgetTotal(ProcurementPr $pr): ?float
    {
        if ($pr->rollout_id !== null) {
            return $this->budgetUtilization->resolveBudgetTotalForRollout((string) $pr->rollout_id);
        }

        if ($pr->project_id !== null) {
            $snapshot = $this->budgetUtilization->snapshotForProject((string) $pr->project_id);

            return $snapshot['budget_total'];
        }

        return null;
    }

    private function sumCommittedForScope(ProcurementPr $pr): float
    {
        if ($pr->rollout_id !== null) {
            return $this->budgetUtilization->sumCommittedForRollout((string) $pr->rollout_id, (string) $pr->id);
        }

        if ($pr->project_id !== null) {
            $snapshot = $this->budgetUtilization->snapshotForProject((string) $pr->project_id);
            if (in_array((string) $pr->status, [ProcurementPrStatus::PENDING_APPROVAL, ProcurementPrStatus::APPROVED], true)) {
                return max(0, round($snapshot['committed'] - (float) $pr->estimated_total, 2));
            }
            if ((string) $pr->status === ProcurementPrStatus::CONVERTED) {
                return max(0, round($snapshot['committed'] - app(ProcurementPoPrBalanceService::class)->openBalanceForPr($pr), 2));
            }

            return $snapshot['committed'];
        }

        return 0.0;
    }
}
