<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementPrLine;
use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementRfqBidLine;
use App\Modules\ProcurementOne\Models\ProcurementRfqLine;
use App\Modules\ProcurementOne\Support\ProcurementLineGridColumns;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps RFQ line items aligned with the linked purchase requisition (source of truth).
 */
final class ProcurementRfqPrLineSyncService
{
    public function syncIfApplicable(ProcurementRfq $rfq): ProcurementRfq
    {
        if ($rfq->pr_id === null || $rfq->pr_id === '') {
            return $rfq;
        }

        if (! in_array((string) $rfq->status, [ProcurementRfqStatus::DRAFT, ProcurementRfqStatus::OPEN], true)) {
            return $rfq;
        }

        $rfq->loadMissing(['purchaseRequisition.lines', 'lines']);

        $pr = $rfq->purchaseRequisition;
        if ($pr === null) {
            return $rfq;
        }

        return DB::connection('tenant')->transaction(function () use ($rfq, $pr): ProcurementRfq {
            $prLines = $pr->lines->sortBy('line_order')->values();
            $rfqLinesByPrId = $rfq->lines->keyBy(static fn (ProcurementRfqLine $line) => (string) ($line->pr_line_id ?? ''));

            $order = 1;
            $seenPrLineIds = [];

            foreach ($prLines as $prLine) {
                $prLineId = (string) $prLine->id;
                $seenPrLineIds[] = $prLineId;

                /** @var ProcurementRfqLine|null $rfqLine */
                $rfqLine = $rfqLinesByPrId->get($prLineId);

                $prMeta = is_array($prLine->metadata_json) ? $prLine->metadata_json : [];

                $payload = [
                    'line_order' => $order++,
                    'description' => (string) $prLine->description,
                    'quantity' => (float) $prLine->quantity,
                    'target_unit_price' => (float) $prLine->unit_price,
                    'metadata_json' => array_merge(is_array($rfqLine?->metadata_json) ? $rfqLine->metadata_json : [], $prMeta, [
                        'synced_from_pr' => true,
                        'removed_from_pr' => false,
                        'pr_line_updated_at' => $prLine->updated_at?->toIso8601String(),
                        'quote_basis' => \App\Modules\ProcurementOne\Support\ProcurementQuoteBasis::fromMetadata($prMeta),
                    ]),
                ];

                if ($rfqLine instanceof ProcurementRfqLine) {
                    if ($rfqLine->uom === null || trim((string) $rfqLine->uom) === '') {
                        $payload['uom'] = $this->resolveUom($prLine);
                    }
                    $rfqLine->fill($payload);
                    $rfqLine->save();
                } else {
                    ProcurementRfqLine::query()->create([
                        'id' => (string) Str::uuid(),
                        'rfq_id' => (string) $rfq->id,
                        'pr_line_id' => $prLineId,
                        'uom' => $this->resolveUom($prLine),
                        ...$payload,
                    ]);
                }
            }

            foreach ($rfq->lines as $rfqLine) {
                $prLineId = (string) ($rfqLine->pr_line_id ?? '');
                if ($prLineId === '' || in_array($prLineId, $seenPrLineIds, true)) {
                    continue;
                }

                if ($this->rfqLineHasBidReferences($rfqLine)) {
                    $metadata = is_array($rfqLine->metadata_json) ? $rfqLine->metadata_json : [];
                    $rfqLine->metadata_json = array_merge($metadata, [
                        'synced_from_pr' => true,
                        'removed_from_pr' => true,
                    ]);
                    $rfqLine->save();

                    continue;
                }

                $rfqLine->delete();
            }

            $rfq->estimated_total = (float) $pr->estimated_total;
            $rfq->currency_code = (string) ($pr->currency ?? $rfq->currency_code ?? 'PHP');

            $metadata = is_array($rfq->metadata_json) ? $rfq->metadata_json : [];
            $rfq->metadata_json = array_merge($metadata, [
                'lines_synced_from_pr_at' => now()->toIso8601String(),
                'lines_source' => 'purchase_requisition',
            ]);
            $rfq->save();

            return $rfq->refresh()->load('lines');
        });
    }

    private function resolveUom(ProcurementPrLine $prLine): string
    {
        return ProcurementLineGridColumns::resolveUom(is_array($prLine->metadata_json) ? $prLine->metadata_json : null);
    }

    private function rfqLineHasBidReferences(ProcurementRfqLine $rfqLine): bool
    {
        return ProcurementRfqBidLine::query()
            ->where('rfq_line_id', $rfqLine->id)
            ->exists();
    }
}
