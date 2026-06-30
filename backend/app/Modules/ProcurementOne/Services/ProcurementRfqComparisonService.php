<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementRfqBid;
use App\Modules\ProcurementOne\Support\ProcurementRfqBidStatus;
use App\Modules\ProcurementOne\Support\ProcurementVendorAccreditationStatus;

final class ProcurementRfqComparisonService
{
    public function __construct(
        private readonly ProcurementRfqScoringPolicyService $scoringPolicy,
    ) {}

    /**
     * @return array{
     *   policy: array<string, mixed>,
     *   rows: list<array<string, mixed>>,
     *   recommended_bid_id: string|null
     * }
     */
    public function matrix(ProcurementRfq $rfq): array
    {
        $policy = $this->scoringPolicy->policy();
        $rfq->loadMissing(['lines', 'bids.vendor', 'bids.lines']);

        $submitted = $rfq->bids->filter(static fn (ProcurementRfqBid $bid) => in_array((string) $bid->status, [
            ProcurementRfqBidStatus::SUBMITTED,
            ProcurementRfqBidStatus::AWARDED,
        ], true));

        if ($submitted->isEmpty()) {
            return [
                'policy' => $policy,
                'rows' => [],
                'recommended_bid_id' => null,
            ];
        }

        $comparisonAmount = static function (ProcurementRfqBid $bid): float {
            $normalized = $bid->normalized_annual_amount;

            return $normalized !== null && (float) $normalized > 0
                ? (float) $normalized
                : (float) $bid->total_amount;
        };

        $lineCount = max(1, $rfq->lines->count());
        $minTotal = (float) $submitted->min(static fn (ProcurementRfqBid $bid) => $comparisonAmount($bid));
        $maxTotal = (float) $submitted->max(static fn (ProcurementRfqBid $bid) => $comparisonAmount($bid));
        $totalSpread = max(0.01, $maxTotal - $minTotal);

        $leadTimes = $submitted->map(static fn (ProcurementRfqBid $bid) => (int) ($bid->avg_lead_time_days ?? 0))->filter(static fn (int $v) => $v > 0);
        $minLead = $leadTimes->isEmpty() ? 0 : (int) $leadTimes->min();
        $maxLead = $leadTimes->isEmpty() ? 0 : (int) $leadTimes->max();
        $leadSpread = max(1, $maxLead - $minLead);

        $rows = [];
        foreach ($submitted as $bid) {
            $quotedLines = $bid->lines->filter(static fn ($line) => (float) $line->quantity > 0)->count();
            $coveragePct = round(($quotedLines / $lineCount) * 100, 1);

            $total = $comparisonAmount($bid);
            $priceScore = round(100 * (1 - (($total - $minTotal) / $totalSpread)), 2);

            $lead = (int) ($bid->avg_lead_time_days ?? 0);
            $leadScore = $leadTimes->isEmpty()
                ? 50.0
                : round(100 * (1 - (($lead - $minLead) / $leadSpread)), 2);

            $accreditation = (string) ($bid->vendor?->accreditation_status ?? ProcurementVendorAccreditationStatus::PENDING);
            $accreditationScore = match ($accreditation) {
                ProcurementVendorAccreditationStatus::ACCREDITED => 100.0,
                ProcurementVendorAccreditationStatus::PENDING => 50.0,
                default => 0.0,
            };

            $coverageScore = $coveragePct;

            $weightedTotal = round(
                ($priceScore * $policy['weight_price']
                    + $leadScore * $policy['weight_lead_time']
                    + $accreditationScore * $policy['weight_accreditation']
                    + $coverageScore * $policy['weight_line_coverage']) / 100,
                2,
            );

            $rows[] = [
                'bid_id' => (string) $bid->id,
                'vendor_id' => (string) $bid->vendor_id,
                'vendor_code' => $bid->vendor?->vendor_code,
                'vendor_name' => $bid->vendor?->company_name,
                'status' => $bid->status,
                'status_label' => ProcurementRfqBidStatus::label((string) $bid->status),
                'total_amount' => (float) $bid->total_amount,
                'total_amount_monthly' => $bid->total_amount_monthly !== null ? (float) $bid->total_amount_monthly : null,
                'total_amount_yearly' => $bid->total_amount_yearly !== null ? (float) $bid->total_amount_yearly : null,
                'normalized_annual_amount' => $bid->normalized_annual_amount !== null
                    ? (float) $bid->normalized_annual_amount
                    : (float) $bid->total_amount,
                'comparison_amount' => $total,
                'currency_code' => $bid->currency_code,
                'avg_lead_time_days' => $bid->avg_lead_time_days,
                'line_coverage_percent' => $coveragePct,
                'accreditation_status' => $accreditation,
                'scores' => [
                    'price' => $priceScore,
                    'lead_time' => $leadScore,
                    'accreditation' => $accreditationScore,
                    'line_coverage' => $coverageScore,
                    'weighted_total' => $weightedTotal,
                ],
            ];
        }

        usort($rows, static fn (array $a, array $b) => ($b['scores']['weighted_total'] <=> $a['scores']['weighted_total']));

        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        return [
            'policy' => $policy,
            'rows' => $rows,
            'recommended_bid_id' => $rows[0]['bid_id'] ?? null,
        ];
    }
}
