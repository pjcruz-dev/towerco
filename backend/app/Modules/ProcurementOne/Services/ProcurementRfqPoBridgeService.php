<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementRfqPoLink;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementRfqPoBridgeService
{
    public function __construct(
        private readonly ProcurementPoService $poService,
        private readonly ProcurementRfqService $rfqService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createPurchaseOrderFromAward(ProcurementRfq $rfq, array $input, TenantUser $actor): ProcurementPo
    {
        abort_unless((string) $rfq->status === ProcurementRfqStatus::AWARDED, 422, __('Create PO from an awarded RFQ only.'));
        abort_if(ProcurementRfqPoLink::query()->where('rfq_id', $rfq->id)->exists(), 422, __('This RFQ already has a purchase order.'));

        $rfq->loadMissing(['awardedBid.lines.rfqLine', 'awardedVendor', 'purchaseRequisition.lines', 'lines']);

        $bid = $rfq->awardedBid;
        if ($bid === null) {
            throw ValidationException::withMessages([
                'rfq' => [__('Awarded bid was not found.')],
            ]);
        }

        $vendor = $rfq->awardedVendor;
        $poLines = [];
        foreach ($bid->lines as $bidLine) {
            $rfqLine = $bidLine->rfqLine;
            if ($rfqLine === null) {
                continue;
            }

            $poLines[] = [
                'description' => $rfqLine->description,
                'uom' => $rfqLine->uom,
                'quantity' => (float) $bidLine->quantity,
                'unit_price' => (float) $bidLine->unit_price,
                'pr_line_id' => $rfqLine->pr_line_id,
                'metadata_json' => array_filter([
                    'quote_basis' => $bidLine->quote_basis,
                    'monthly_unit_price' => $bidLine->monthly_unit_price,
                    'yearly_unit_price' => $bidLine->yearly_unit_price,
                    'amount_monthly' => $bidLine->amount_monthly,
                    'amount_yearly' => $bidLine->amount_yearly,
                ], static fn ($value) => $value !== null && $value !== ''),
            ];
        }

        if ($poLines === []) {
            throw ValidationException::withMessages([
                'lines' => [__('Awarded bid has no quotable lines.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($rfq, $input, $actor, $vendor, $poLines, $bid): ProcurementPo {
            $pr = $rfq->purchaseRequisition;
            abort_if($pr === null, 422, __('RFQ is not linked to a purchase requisition.'));

            $deliveryLocation = $this->resolveDeliveryLocation($input, $rfq, $pr);
            $deliveryDate = $this->resolveDeliveryDate($input, $rfq);

            $po = $this->poService->createFromPurchaseRequisition($pr, [
                'vendor_code' => $vendor?->vendor_code ?? $input['vendor_code'] ?? null,
                'vendor_name' => $vendor?->company_name ?? $input['vendor_name'] ?? null,
                'supplier' => $vendor?->company_name ?? $input['supplier'] ?? null,
                'ship_to' => $input['ship_to'] ?? $deliveryLocation,
                'delivery_date' => $deliveryDate,
                'delivery_location' => $deliveryLocation,
                'payment_terms' => $input['payment_terms'] ?? null,
                'currency_code' => $bid->currency_code ?? $rfq->currency_code,
                'lines' => $poLines,
            ], $actor);

            ProcurementRfqPoLink::query()->create([
                'rfq_id' => (string) $rfq->id,
                'po_id' => (string) $po->id,
                'bid_id' => (string) $bid->id,
                'allocated_amount' => (float) $po->grand_total,
            ]);

            $this->rfqService->markConverted($rfq, (string) $po->id, $actor);

            return $po->refresh()->load(['lines', 'prLinks']);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveDeliveryDate(array $input, ProcurementRfq $rfq): string
    {
        $explicit = trim((string) ($input['delivery_date'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        if ($rfq->bidding_closes_at !== null) {
            return $rfq->bidding_closes_at->copy()->addDays(14)->toDateString();
        }

        return now()->addDays(30)->toDateString();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveDeliveryLocation(array $input, ProcurementRfq $rfq, ProcurementPr $pr): ?string
    {
        foreach (['delivery_location', 'ship_to'] as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $siteId = $rfq->site_id ?? $pr->site_id;
        if ($siteId === null || $siteId === '') {
            return null;
        }

        $site = Site::query()->find($siteId);
        if ($site === null) {
            return null;
        }

        $label = trim((string) ($site->name ?? ''));
        if ($label === '') {
            return null;
        }

        $code = trim((string) ($site->site_code ?? ''));
        if ($code !== '') {
            return "{$code} — {$label}";
        }

        return $label;
    }
}
