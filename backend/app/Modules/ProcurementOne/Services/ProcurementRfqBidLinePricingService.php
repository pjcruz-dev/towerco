<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementRfqLine;
use App\Modules\ProcurementOne\Support\ProcurementQuoteBasis;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ProcurementRfqBidLinePricingService
{
    /**
     * @param  array<string, mixed>  $lineInput
     * @return array{
     *     rfq_line_id: string,
     *     quantity: float,
     *     unit_price: float,
     *     monthly_unit_price: ?float,
     *     yearly_unit_price: ?float,
     *     amount: float,
     *     amount_monthly: ?float,
     *     amount_yearly: ?float,
     *     normalized_annual_amount: float,
     *     quote_basis: string,
     *     lead_time_days: ?int,
     *     notes: ?string
     * }
     */
    public function normalizeLine(array $lineInput, ProcurementRfqLine $rfqLine): array
    {
        $quoteBasis = ProcurementQuoteBasis::fromMetadata(
            is_array($rfqLine->metadata_json) ? $rfqLine->metadata_json : null,
        );

        $quantity = round(max(0, (float) ($lineInput['quantity'] ?? $rfqLine->quantity)), 4);
        $unitPrice = round(max(0, (float) ($lineInput['unit_price'] ?? 0)), 2);
        $monthlyUnitPrice = array_key_exists('monthly_unit_price', $lineInput)
            ? round(max(0, (float) $lineInput['monthly_unit_price']), 2)
            : null;
        $yearlyUnitPrice = array_key_exists('yearly_unit_price', $lineInput)
            ? round(max(0, (float) $lineInput['yearly_unit_price']), 2)
            : null;

        if (ProcurementQuoteBasis::requiresUnitPrice($quoteBasis)) {
            if ($quantity > 0 && $unitPrice <= 0) {
                throw ValidationException::withMessages([
                    'lines' => [__('Enter a unit price for :line.', ['line' => $rfqLine->description])],
                ]);
            }
        }

        if (ProcurementQuoteBasis::allowsMonthly($quoteBasis) && $quantity > 0 && ($monthlyUnitPrice === null || $monthlyUnitPrice <= 0)) {
            if ($quoteBasis === ProcurementQuoteBasis::MONTHLY) {
                throw ValidationException::withMessages([
                    'lines' => [__('Enter a monthly unit price for :line.', ['line' => $rfqLine->description])],
                ]);
            }
        }

        if (ProcurementQuoteBasis::allowsYearly($quoteBasis) && $quantity > 0 && ($yearlyUnitPrice === null || $yearlyUnitPrice <= 0)) {
            if ($quoteBasis === ProcurementQuoteBasis::YEARLY) {
                throw ValidationException::withMessages([
                    'lines' => [__('Enter a yearly unit price for :line.', ['line' => $rfqLine->description])],
                ]);
            }
        }

        if ($quoteBasis === ProcurementQuoteBasis::MONTHLY_YEARLY && $quantity > 0) {
            $hasMonthly = $monthlyUnitPrice !== null && $monthlyUnitPrice > 0;
            $hasYearly = $yearlyUnitPrice !== null && $yearlyUnitPrice > 0;
            if (! $hasMonthly && ! $hasYearly) {
                throw ValidationException::withMessages([
                    'lines' => [__('Enter a monthly or yearly unit price for :line.', ['line' => $rfqLine->description])],
                ]);
            }
        }

        $amountMonthly = $monthlyUnitPrice !== null && $monthlyUnitPrice > 0
            ? round($quantity * $monthlyUnitPrice, 2)
            : null;
        $amountYearly = $yearlyUnitPrice !== null && $yearlyUnitPrice > 0
            ? round($quantity * $yearlyUnitPrice, 2)
            : null;

        if (! ProcurementQuoteBasis::requiresUnitPrice($quoteBasis)) {
            if ($quoteBasis === ProcurementQuoteBasis::MONTHLY) {
                $unitPrice = (float) ($monthlyUnitPrice ?? 0);
            } elseif ($quoteBasis === ProcurementQuoteBasis::YEARLY) {
                $unitPrice = (float) ($yearlyUnitPrice ?? 0);
            } elseif ($amountYearly !== null && $amountYearly > 0) {
                $unitPrice = (float) ($yearlyUnitPrice ?? 0);
            } elseif ($amountMonthly !== null && $amountMonthly > 0) {
                $unitPrice = (float) ($monthlyUnitPrice ?? 0);
            }
        }

        $amount = round($quantity * $unitPrice, 2);
        $normalizedAnnual = ProcurementQuoteBasis::normalizedAnnualAmount(
            $quoteBasis,
            $quantity,
            $unitPrice,
            $monthlyUnitPrice,
            $yearlyUnitPrice,
        );

        return [
            'rfq_line_id' => (string) $rfqLine->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'monthly_unit_price' => $amountMonthly !== null ? (float) ($monthlyUnitPrice ?? 0) : null,
            'yearly_unit_price' => $amountYearly !== null ? (float) ($yearlyUnitPrice ?? 0) : null,
            'amount' => $amount,
            'amount_monthly' => $amountMonthly,
            'amount_yearly' => $amountYearly,
            'normalized_annual_amount' => $normalizedAnnual,
            'quote_basis' => $quoteBasis,
            'lead_time_days' => isset($lineInput['lead_time_days']) ? max(0, (int) $lineInput['lead_time_days']) : null,
            'notes' => isset($lineInput['notes']) ? trim((string) $lineInput['notes']) : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $normalizedLines
     * @return array{
     *     total_amount: float,
     *     total_amount_monthly: ?float,
     *     total_amount_yearly: ?float,
     *     normalized_annual_amount: float
     * }
     */
    public function summarizeBid(array $normalizedLines): array
    {
        $totalAmount = 0.0;
        $totalMonthly = 0.0;
        $totalYearly = 0.0;
        $normalizedAnnual = 0.0;
        $hasMonthly = false;
        $hasYearly = false;

        foreach ($normalizedLines as $line) {
            $totalAmount += (float) ($line['amount'] ?? 0);
            $normalizedAnnual += (float) ($line['normalized_annual_amount'] ?? 0);

            if (($line['amount_monthly'] ?? null) !== null) {
                $hasMonthly = true;
                $totalMonthly += (float) $line['amount_monthly'];
            }

            if (($line['amount_yearly'] ?? null) !== null) {
                $hasYearly = true;
                $totalYearly += (float) $line['amount_yearly'];
            }
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'total_amount_monthly' => $hasMonthly ? round($totalMonthly, 2) : null,
            'total_amount_yearly' => $hasYearly ? round($totalYearly, 2) : null,
            'normalized_annual_amount' => round($normalizedAnnual, 2),
        ];
    }

    /**
     * @param  Collection<int, ProcurementRfqLine>  $rfqLines
     */
    public function rfqLineMap(Collection $rfqLines): Collection
    {
        return $rfqLines->keyBy(static fn (ProcurementRfqLine $line) => (string) $line->id);
    }
}
