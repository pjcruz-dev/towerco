<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementGrn;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementComposeMetadata;
use App\Modules\ProcurementOne\Support\ProcurementGrnStatus;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementApInvoiceService
{
    public function __construct(
        private readonly ProcurementApInvoiceMatchPolicyService $matchPolicy,
        private readonly ProcurementApInvoiceMatchingService $matching,
        private readonly ProcurementPoLineCalculator $calculator,
        private readonly ProcurementApInvoiceValueMapper $mapper,
        private readonly ProcurementApInvoiceRegistryService $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $composeFormValues
     * @return array{invoice: ProcurementApInvoice, warning: string|null}
     */
    public function createFromPurchaseOrder(ProcurementPo $po, array $input, TenantUser $actor, ?array $composeFormValues = null): array
    {
        $this->assertPoInvoiceable($po);

        return DB::connection('tenant')->transaction(function () use ($po, $input, $actor, $composeFormValues): array {
            $policy = $this->matchPolicy->policy();
            $grnId = $input['grn_id'] ?? null;
            if ($grnId !== null) {
                $grn = ProcurementGrn::query()->find($grnId);
                abort_if($grn === null || (string) $grn->po_id !== (string) $po->id, 422, __('GRN must belong to the purchase order.'));
                abort_if((string) $grn->status !== ProcurementGrnStatus::POSTED, 422, __('Only posted goods receipts can be matched.'));
            }

            $lines = $this->normalizeLines($po, $input['lines'] ?? [], $grnId);
            $totals = $this->calculator->calculate(
                $lines,
                (float) ($input['vat_exempt_amount'] ?? 0),
                (float) ($input['zero_rated_amount'] ?? 0),
                (float) ($input['vat_rate'] ?? $po->vat_rate ?? 12),
                (float) ($input['less_discount'] ?? 0),
            );

            $invoice = ProcurementApInvoice::query()->create([
                'status' => ProcurementApInvoiceStatus::DRAFT,
                'po_id' => (string) $po->id,
                'grn_id' => $grnId,
                'vendor_code' => $input['vendor_code'] ?? $po->vendor_code,
                'vendor_name' => $input['vendor_name'] ?? $po->vendor_name ?? $po->supplier,
                'vendor_invoice_no' => $input['vendor_invoice_no'] ?? null,
                'invoice_date' => $input['invoice_date'] ?? now()->toDateString(),
                'due_date' => $input['due_date'] ?? null,
                'payment_terms' => $input['payment_terms'] ?? $po->payment_terms,
                'currency_code' => (string) ($input['currency_code'] ?? $po->currency_code ?? 'PHP'),
                'exchange_rate' => (float) ($input['exchange_rate'] ?? $po->exchange_rate ?? 1),
                'vatable_amount' => $totals['vatable_amount'],
                'vat_exempt_amount' => (float) ($input['vat_exempt_amount'] ?? 0),
                'zero_rated_amount' => (float) ($input['zero_rated_amount'] ?? 0),
                'vat_rate' => (float) ($input['vat_rate'] ?? $po->vat_rate ?? 12),
                'vat_amount' => $totals['vat_amount'],
                'total_vat_inclusive' => $totals['total_vat_inclusive'],
                'less_discount' => (float) ($input['less_discount'] ?? 0),
                'grand_total' => $totals['grand_total'],
                'match_mode' => (string) ($input['match_mode'] ?? $policy['match_mode']),
                'match_status' => 'pending',
                'requestor_id' => (string) $actor->id,
                'notes' => $input['notes'] ?? null,
            ]);

            $this->mapper->syncLines($invoice, $this->mapCalculatedLines($lines, $totals['lines'], $input['lines'] ?? []));
            $this->persistComposeFormValues($invoice, $composeFormValues);

            $evaluation = $this->matching->evaluate($invoice->refresh()->load('lines'));
            $invoice->match_status = $evaluation['match_status'];
            $invoice->match_variance_amount = $evaluation['variance_amount'];
            $invoice->metadata_json = ['match' => $evaluation];
            $invoice->save();

            return [
                'invoice' => $this->registry->find((string) $invoice->id) ?? $invoice->refresh()->load(['lines', 'purchaseOrder', 'goodsReceipt']),
                'warning' => $evaluation['warning'],
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $composeFormValues
     */
    public function updateDraft(ProcurementApInvoice $invoice, array $input, TenantUser $actor, ?array $composeFormValues = null): ProcurementApInvoice
    {
        abort_unless(ProcurementApInvoiceStatus::isEditable((string) $invoice->status), 422, __('Only draft AP invoices can be edited.'));

        return DB::connection('tenant')->transaction(function () use ($invoice, $input, $composeFormValues): ProcurementApInvoice {
            $po = $invoice->purchaseOrder()->with('lines')->firstOrFail();

            $invoice->fill(array_filter([
                'grn_id' => $input['grn_id'] ?? null,
                'vendor_invoice_no' => $input['vendor_invoice_no'] ?? null,
                'invoice_date' => $input['invoice_date'] ?? null,
                'due_date' => $input['due_date'] ?? null,
                'payment_terms' => $input['payment_terms'] ?? null,
                'notes' => $input['notes'] ?? null,
            ], static fn ($value) => $value !== null));

            if (array_key_exists('lines', $input)) {
                $lines = $this->normalizeLines($po, $input['lines'], $invoice->grn_id);
                $totals = $this->calculator->calculate(
                    $lines,
                    (float) ($input['vat_exempt_amount'] ?? $invoice->vat_exempt_amount),
                    (float) ($input['zero_rated_amount'] ?? $invoice->zero_rated_amount),
                    (float) ($input['vat_rate'] ?? $invoice->vat_rate),
                    (float) ($input['less_discount'] ?? $invoice->less_discount),
                );
                $invoice->fill([
                    'vatable_amount' => $totals['vatable_amount'],
                    'vat_amount' => $totals['vat_amount'],
                    'total_vat_inclusive' => $totals['total_vat_inclusive'],
                    'grand_total' => $totals['grand_total'],
                ]);
                $this->mapper->syncLines($invoice, $this->mapCalculatedLines($lines, $totals['lines'], $input['lines']));
            }

            $invoice->save();
            $this->persistComposeFormValues($invoice, $composeFormValues);
            $evaluation = $this->matching->evaluate($invoice->refresh()->load('lines'));
            $invoice->match_status = $evaluation['match_status'];
            $invoice->match_variance_amount = $evaluation['variance_amount'];
            $invoice->metadata_json = array_merge($invoice->metadata_json ?? [], ['match' => $evaluation]);
            $invoice->save();

            return $this->registry->find((string) $invoice->id) ?? $invoice->refresh()->load(['lines', 'purchaseOrder', 'goodsReceipt']);
        });
    }

    private function assertPoInvoiceable(ProcurementPo $po): void
    {
        if (! in_array((string) $po->status, [
            ProcurementPoStatus::APPROVED,
            ProcurementPoStatus::SENT,
            ProcurementPoStatus::PARTIALLY_RECEIVED,
            ProcurementPoStatus::RECEIVED,
        ], true)) {
            throw ValidationException::withMessages([
                'po_id' => [__('Purchase order must be approved before invoicing.')],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $inputLines
     * @return list<array<string, mixed>>
     */
    private function normalizeLines(ProcurementPo $po, array $inputLines, ?string $grnId): array
    {
        if ($inputLines === []) {
            return $po->lines->map(static fn (ProcurementPoLine $line) => [
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'discount' => (float) $line->discount,
                'po_line_id' => (string) $line->id,
            ])->all();
        }

        $normalized = [];
        foreach ($inputLines as $line) {
            $poLineId = (string) ($line['po_line_id'] ?? '');
            $poLine = $po->lines->firstWhere('id', $poLineId);
            abort_if($poLine === null, 422, __('PO line not found.'));

            $normalized[] = [
                'description' => $line['description'] ?? $poLine->description,
                'uom' => $line['uom'] ?? $poLine->uom,
                'quantity' => (float) ($line['quantity_invoiced'] ?? $line['quantity'] ?? $poLine->quantity),
                'unit_price' => (float) ($line['unit_price'] ?? $poLine->unit_price),
                'discount' => (float) ($line['discount'] ?? $poLine->discount),
                'po_line_id' => $poLineId,
                'grn_line_id' => $line['grn_line_id'] ?? null,
                'cost_center_id' => $line['cost_center_id'] ?? $poLine->cost_center_id,
                'expense_type' => $line['expense_type'] ?? $poLine->expense_type,
                'budget_line_id' => $line['budget_line_id'] ?? $poLine->budget_line_id,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $sourceLines
     * @param  list<array<string, mixed>>  $calculatedLines
     * @param  list<array<string, mixed>>  $inputLines
     * @return list<array<string, mixed>>
     */
    private function mapCalculatedLines(array $sourceLines, array $calculatedLines, array $inputLines): array
    {
        $mapped = [];
        foreach ($calculatedLines as $index => $calc) {
            $source = $sourceLines[$index] ?? [];
            $mapped[] = [
                'po_line_id' => $source['po_line_id'] ?? null,
                'grn_line_id' => $source['grn_line_id'] ?? null,
                'description' => $calc['description'],
                'uom' => $calc['uom'],
                'quantity_invoiced' => $calc['quantity'],
                'unit_price' => $calc['unit_price'],
                'discount' => $calc['discount'],
                'cost_center_id' => $source['cost_center_id'] ?? null,
                'expense_type' => $source['expense_type'] ?? null,
                'budget_line_id' => $source['budget_line_id'] ?? null,
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>|null  $composeFormValues
     */
    private function persistComposeFormValues(ProcurementApInvoice $invoice, ?array $composeFormValues): void
    {
        if ($composeFormValues === null) {
            return;
        }

        $invoice->metadata_json = ProcurementComposeMetadata::withComposeFormValues(
            is_array($invoice->metadata_json) ? $invoice->metadata_json : [],
            $composeFormValues,
        );
        $invoice->save();
    }
}
