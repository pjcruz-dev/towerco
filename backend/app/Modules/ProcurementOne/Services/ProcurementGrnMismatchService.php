<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementGrn;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use App\Modules\ProcurementOne\Support\ProcurementGrnStatus;

final class ProcurementGrnMismatchService
{
    public function __construct(
        private readonly ProcurementGrnPoBalanceService $balances,
        private readonly ProcurementGrnReceiptPolicyService $receiptPolicy,
    ) {}

    /**
     * @return list<array{
     *   type: string,
     *   severity: string,
     *   message: string,
     *   po_line_id?: string,
     *   description?: string,
     *   quantity_received?: float,
     *   quantity_remaining_before?: float,
     *   quantity_ordered?: float,
     *   quantity_cumulative?: float
     * }>
     */
    public function analyze(ProcurementGrn $grn, ?ProcurementPo $po = null, ?string $excludeGrnId = null): array
    {
        $grn->loadMissing(['lines']);
        $po ??= $grn->purchaseOrder()->with('lines')->first();
        if ($po === null) {
            return [];
        }

        $items = [];
        $seen = [];

        foreach ($grn->lines as $line) {
            $received = (float) $line->quantity_received;
            if ($received <= 0) {
                continue;
            }

            $poLine = $po->lines->firstWhere('id', $line->po_line_id);
            if (! $poLine instanceof ProcurementPoLine) {
                continue;
            }

            $ordered = (float) $poLine->quantity;
            $remainingBefore = $this->balances->remainingQuantityForPoLine($poLine, $excludeGrnId ?? (string) $grn->id);

            if ($received > $remainingBefore + 0.0001) {
                $key = 'open_balance:'.(string) $poLine->id;
                if (! isset($seen[$key])) {
                    $items[] = [
                        'type' => 'tolerance_over_receipt',
                        'severity' => 'warning',
                        'po_line_id' => (string) $poLine->id,
                        'description' => $line->description,
                        'quantity_received' => $received,
                        'quantity_remaining_before' => $remainingBefore,
                        'quantity_ordered' => $ordered,
                        'message' => __('Received :received exceeds open PO balance :remaining for :description.', [
                            'received' => number_format($received, 4),
                            'remaining' => number_format($remainingBefore, 4),
                            'description' => $line->description,
                        ]),
                    ];
                    $seen[$key] = true;
                }
            }

            if ((string) $grn->status === ProcurementGrnStatus::POSTED) {
                $cumulative = $this->balances->receivedQuantityForPoLine((string) $poLine->id);
                $policy = $this->receiptPolicy->policy();
                $allowedMax = $ordered + ($ordered * ($policy['tolerance_percent'] / 100));

                if ($cumulative > $allowedMax + 0.0001) {
                    $key = 'cumulative:'.(string) $poLine->id;
                    if (! isset($seen[$key])) {
                        $items[] = [
                            'type' => 'cumulative_over_receipt',
                            'severity' => 'critical',
                            'po_line_id' => (string) $poLine->id,
                            'description' => $line->description,
                            'quantity_ordered' => $ordered,
                            'quantity_cumulative' => $cumulative,
                            'message' => __('Cumulative received :received exceeds allowed maximum :max for :description.', [
                                'received' => number_format($cumulative, 4),
                                'max' => number_format($allowedMax, 4),
                                'description' => $line->description,
                            ]),
                        ];
                        $seen[$key] = true;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resolveForGrn(ProcurementGrn $grn): array
    {
        $metadata = is_array($grn->metadata_json) ? $grn->metadata_json : [];
        $stored = $metadata['mismatches'] ?? null;

        if (is_array($stored) && $stored !== [] && (string) $grn->status === ProcurementGrnStatus::POSTED) {
            return array_values(array_filter($stored, static fn ($row) => is_array($row)));
        }

        return $this->analyze($grn);
    }

    /**
     * @param  list<array<string, mixed>>  $mismatches
     * @return array<string, mixed>
     */
    public function persistOnPost(ProcurementGrn $grn, ?string $warning, array $mismatches): array
    {
        $metadata = is_array($grn->metadata_json) ? $grn->metadata_json : [];

        if ($warning !== null && $warning !== '') {
            $metadata['receipt_warning'] = $warning;
            $hasTolerance = false;
            foreach ($mismatches as $row) {
                if (($row['type'] ?? '') === 'tolerance_over_receipt') {
                    $hasTolerance = true;
                    break;
                }
            }
            if (! $hasTolerance) {
                $mismatches[] = [
                    'type' => 'tolerance_over_receipt',
                    'severity' => 'warning',
                    'message' => $warning,
                ];
            }
        }

        $metadata['mismatches'] = $mismatches;

        return $metadata;
    }
}
