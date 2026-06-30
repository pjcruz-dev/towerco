<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementContract;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementContractStatus;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use Illuminate\Validation\ValidationException;

final class ProcurementContractSpendService
{
    public function __construct(
        private readonly ProcurementContractSpendPolicyService $policy,
    ) {}

    public function committedForContract(ProcurementContract $contract, ?string $excludePoId = null): float
    {
        $query = ProcurementPo::query()
            ->where('contract_id', $contract->id)
            ->whereNotIn('status', [
                ProcurementPoStatus::CANCELLED,
                ProcurementPoStatus::VOIDED,
            ]);

        if ($excludePoId !== null) {
            $query->where('id', '<>', $excludePoId);
        }

        return (float) $query->sum('grand_total');
    }

    public function openCeiling(ProcurementContract $contract, ?string $excludePoId = null): ?float
    {
        if ($contract->spend_ceiling === null) {
            return null;
        }

        $committed = $this->committedForContract($contract, $excludePoId);

        return max(0, round((float) $contract->spend_ceiling - $committed, 2));
    }

    public function refreshCommittedAmount(ProcurementContract $contract): void
    {
        $contract->committed_po_amount = round($this->committedForContract($contract), 2);
        $contract->save();
    }

    public function refreshForPurchaseOrder(ProcurementPo $po): void
    {
        if ($po->contract_id === null) {
            return;
        }

        $contract = ProcurementContract::query()->find($po->contract_id);
        if ($contract instanceof ProcurementContract) {
            $this->refreshCommittedAmount($contract);
        }
    }

    /**
     * @return array{
     *     blocked: bool,
     *     warning: string|null,
     *     spend_ceiling: float|null,
     *     committed: float|null,
     *     available: float|null
     * }
     */
    public function evaluate(ProcurementContract $contract, float $amount, ?string $excludePoId = null): array
    {
        if ($contract->spend_ceiling === null || ! $this->policy->isEnforced()) {
            return $this->emptyEvaluation($contract);
        }

        $committed = $this->committedForContract($contract, $excludePoId);
        $available = max(0, round((float) $contract->spend_ceiling - $committed, 2));
        $ceiling = (float) $contract->spend_ceiling;

        if ($amount <= $available + 0.0001) {
            return [
                'blocked' => false,
                'warning' => null,
                'spend_ceiling' => $ceiling,
                'committed' => $committed,
                'available' => $available,
            ];
        }

        $message = __(
            'PO total :amount exceeds available contract spend of :available (ceiling :ceiling, committed :committed).',
            [
                'amount' => number_format($amount, 2, '.', ''),
                'available' => number_format($available, 2, '.', ''),
                'ceiling' => number_format($ceiling, 2, '.', ''),
                'committed' => number_format($committed, 2, '.', ''),
            ],
        );

        if ($this->policy->blocksOverCeiling()) {
            throw ValidationException::withMessages([
                'grand_total' => [$message],
            ]);
        }

        return [
            'blocked' => false,
            'warning' => $message,
            'spend_ceiling' => $ceiling,
            'committed' => $committed,
            'available' => $available,
        ];
    }

    public function assertSpendAllowed(ProcurementContract $contract, float $amount, ?string $excludePoId = null): ?string
    {
        $evaluation = $this->evaluate($contract, $amount, $excludePoId);

        return $evaluation['warning'];
    }

    public function resolveActiveContract(string $contractId): ProcurementContract
    {
        $contract = ProcurementContract::query()->with('vendor')->find($contractId);
        abort_if($contract === null, 422, __('Selected contract was not found.'));
        abort_unless((string) $contract->status === ProcurementContractStatus::ACTIVE, 422, __('Only active contracts can be linked to purchase orders.'));

        return $contract;
    }

    public function assertVendorMatches(ProcurementContract $contract, ?string $vendorCode, ?string $vendorName): void
    {
        $vendor = $contract->vendor;
        if (! $vendor instanceof ProcurementVendor) {
            return;
        }

        $expectedCode = trim((string) $vendor->vendor_code);
        $expectedName = trim((string) $vendor->company_name);
        $actualCode = trim((string) ($vendorCode ?? ''));
        $actualName = trim((string) ($vendorName ?? ''));

        if ($actualCode !== '' && $expectedCode !== '' && strcasecmp($actualCode, $expectedCode) !== 0) {
            throw ValidationException::withMessages([
                'vendor_code' => [__('Purchase order vendor does not match the selected contract vendor.')],
            ]);
        }

        if ($actualCode === '' && $actualName !== '' && $expectedName !== '' && strcasecmp($actualName, $expectedName) !== 0) {
            throw ValidationException::withMessages([
                'vendor_name' => [__('Purchase order vendor does not match the selected contract vendor.')],
            ]);
        }
    }

    /**
     * @return array{blocked: bool, warning: string|null, spend_ceiling: float|null, committed: float|null, available: float|null}
     */
    private function emptyEvaluation(ProcurementContract $contract): array
    {
        $committed = $this->committedForContract($contract);

        return [
            'blocked' => false,
            'warning' => null,
            'spend_ceiling' => $contract->spend_ceiling !== null ? (float) $contract->spend_ceiling : null,
            'committed' => $committed,
            'available' => $contract->spend_ceiling !== null
                ? max(0, round((float) $contract->spend_ceiling - $committed, 2))
                : null,
        ];
    }
}
