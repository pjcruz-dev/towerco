<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementContract;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementComposeMetadata;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementPoService
{
    public function __construct(
        private readonly ProcurementPoFormResolverService $formResolver,
        private readonly ProcurementPoLineCalculator $calculator,
        private readonly ProcurementPoValueMapper $mapper,
        private readonly ProcurementPoSubmissionBridgeService $bridge,
        private readonly ProcurementPoPrBalanceService $balances,
        private readonly ProcurementPoRegistryService $registry,
        private readonly ProcurementContractSpendService $contractSpend,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $composeFormValues
     */
    public function create(array $input, TenantUser $actor, ?array $composeFormValues = null): ProcurementPo
    {
        $prIds = array_values(array_unique(array_filter(
            array_map('strval', $input['pr_ids'] ?? []),
            static fn (string $id) => $id !== '',
        )));

        abort_if($prIds === [], 422, __('At least one approved purchase requisition is required.'));

        $prs = $this->balances->resolveApprovedPurchaseRequisitions($prIds);

        return $this->createForPurchaseRequisitions($prs, $input, $actor);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $composeFormValues
     */
    public function createFromPurchaseRequisition(ProcurementPr $pr, array $input, TenantUser $actor, ?array $composeFormValues = null): ProcurementPo
    {
        return $this->createForPurchaseRequisitions([$pr], $input, $actor, $composeFormValues);
    }

    /**
     * @param  list<ProcurementPr>  $prs
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $composeFormValues
     */
    private function createForPurchaseRequisitions(array $prs, array $input, TenantUser $actor, ?array $composeFormValues = null): ProcurementPo
    {
        $form = $this->formResolver->resolvePublishedFormOrFail();
        $lines = $this->normalizeLines($input['lines'] ?? [], $prs);
        $totals = $this->calculator->calculate(
            $lines,
            (float) ($input['vat_exempt_amount'] ?? 0),
            (float) ($input['zero_rated_amount'] ?? 0),
            (float) ($input['vat_rate'] ?? 12),
            (float) ($input['less_discount'] ?? 0),
        );

        $allocations = $this->resolveAllocations($prs, $totals['vatable_amount'], $lines, $input['allocations'] ?? null);

        foreach ($allocations as $prId => $amount) {
            $pr = collect($prs)->firstWhere('id', $prId);
            if ($pr instanceof ProcurementPr) {
                $this->balances->assertAllocationAllowed($pr, $amount);
            }
        }

        $contract = $this->resolveContractForPurchaseOrder($input, $totals['grand_total']);

        return DB::connection('tenant')->transaction(function () use ($input, $actor, $form, $totals, $allocations, $prs, $contract, $composeFormValues): ProcurementPo {
            $po = ProcurementPo::query()->create([
                'status' => ProcurementPoStatus::DRAFT,
                'e_approval_form_id' => (string) $form->id,
                'requestor_id' => (string) $actor->id,
                'contract_id' => $contract?->id,
                'vendor_code' => $input['vendor_code'] ?? null,
                'vendor_name' => $input['vendor_name'] ?? null,
                'supplier' => $input['supplier'] ?? null,
                'ship_to' => $input['ship_to'] ?? null,
                'delivery_date' => $input['delivery_date'] ?? null,
                'payment_terms' => $input['payment_terms'] ?? null,
                'currency_code' => (string) ($input['currency_code'] ?? 'PHP'),
                'exchange_rate' => (float) ($input['exchange_rate'] ?? 1),
                'delivery_location' => $input['delivery_location'] ?? null,
                'vatable_amount' => $totals['vatable_amount'],
                'vat_exempt_amount' => (float) ($input['vat_exempt_amount'] ?? 0),
                'zero_rated_amount' => (float) ($input['zero_rated_amount'] ?? 0),
                'vat_rate' => (float) ($input['vat_rate'] ?? 12),
                'vat_amount' => $totals['vat_amount'],
                'total_vat_inclusive' => $totals['total_vat_inclusive'],
                'less_discount' => (float) ($input['less_discount'] ?? 0),
                'grand_total' => $totals['grand_total'],
                'total_amount' => $totals['total_amount'],
                'metadata_json' => ProcurementComposeMetadata::withComposeFormValues([
                    'linked_pr_ids' => array_map(static fn (ProcurementPr $pr) => (string) $pr->id, $prs),
                    'consolidated' => count($prs) > 1,
                ], $composeFormValues),
            ]);

            $this->mapper->syncLines($po, array_map(static function (array $line) use ($prs): array {
                if (! isset($line['pr_id']) && count($prs) === 1) {
                    $line['pr_id'] = (string) $prs[0]->id;
                }

                return $line;
            }, $totals['lines']));

            $parentSubmissionId = $this->primaryParentSubmissionId($prs);
            $prDocumentNo = $prs[0]->document_no ?? null;

            $this->bridge->ensureDraftSubmission(
                $po->refresh()->load(['lines']),
                $actor,
                $parentSubmissionId,
                $prDocumentNo,
            );

            foreach ($allocations as $prId => $amount) {
                $pr = collect($prs)->firstWhere('id', $prId);
                if ($pr instanceof ProcurementPr) {
                    $this->balances->syncPrLink($po, $pr, $amount);
                }
            }

            $this->balances->refreshPurchaseRequisitionStatuses($po);

            $this->contractSpend->refreshForPurchaseOrder($po);

            return $this->registry->find((string) $po->id) ?? $po;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $composeFormValues
     */
    public function update(ProcurementPo $po, array $input, TenantUser $actor, ?array $composeFormValues = null): ProcurementPo
    {
        if (array_key_exists('status', $input) && $input['status'] !== null) {
            return $this->transitionStatus($po, (string) $input['status'], $actor);
        }

        if (! ProcurementPoStatus::isEditable((string) $po->status)) {
            throw ValidationException::withMessages([
                'status' => [__('Only draft purchase orders can be edited.')],
            ]);
        }

        if ((string) $po->requestor_id !== (string) $actor->id && ! $actor->can('procurement_one:documents:manage')) {
            throw ValidationException::withMessages([
                'po' => [__('You cannot edit this purchase order.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($po, $input, $actor, $composeFormValues): ProcurementPo {
            $po->loadMissing(['lines', 'prLinks.purchaseRequisition']);
            $prs = $po->prLinks->map(static fn ($link) => $link->purchaseRequisition)->filter()->values()->all();

            $lines = array_key_exists('lines', $input)
                ? $this->normalizeLines($input['lines'], $prs)
                : $po->lines->map(static fn ($line) => [
                    'item' => $line->item,
                    'description' => $line->description,
                    'uom' => $line->uom,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'discount' => (float) $line->discount,
                    'pr_id' => $line->pr_id,
                    'pr_line_id' => $line->pr_line_id,
                ])->all();

            $totals = $this->calculator->calculate(
                $lines,
                (float) ($input['vat_exempt_amount'] ?? $po->vat_exempt_amount),
                (float) ($input['zero_rated_amount'] ?? $po->zero_rated_amount),
                (float) ($input['vat_rate'] ?? $po->vat_rate),
                (float) ($input['less_discount'] ?? $po->less_discount),
            );

            $allocations = $this->resolveAllocations(
                $prs,
                $totals['vatable_amount'],
                $lines,
                $input['allocations'] ?? null,
            );

            foreach ($allocations as $prId => $amount) {
                $pr = collect($prs)->firstWhere('id', $prId);
                if ($pr instanceof ProcurementPr) {
                    $this->balances->assertAllocationAllowed($pr, $amount, (string) $po->id);
                }
            }

            $contract = $this->resolveContractForPurchaseOrder($input, $totals['grand_total'], $po);

            $po->fill(array_filter([
                'vendor_code' => $input['vendor_code'] ?? null,
                'vendor_name' => $input['vendor_name'] ?? null,
                'supplier' => $input['supplier'] ?? null,
                'ship_to' => $input['ship_to'] ?? null,
                'delivery_date' => $input['delivery_date'] ?? null,
                'payment_terms' => $input['payment_terms'] ?? null,
                'currency_code' => $input['currency_code'] ?? null,
                'exchange_rate' => $input['exchange_rate'] ?? null,
                'delivery_location' => $input['delivery_location'] ?? null,
                'vat_exempt_amount' => $input['vat_exempt_amount'] ?? null,
                'zero_rated_amount' => $input['zero_rated_amount'] ?? null,
                'vat_rate' => $input['vat_rate'] ?? null,
                'less_discount' => $input['less_discount'] ?? null,
                'contract_id' => array_key_exists('contract_id', $input)
                    ? ($input['contract_id'] === null ? null : (string) $input['contract_id'])
                    : null,
            ], static fn ($value) => $value !== null));

            if ($contract !== null) {
                $po->contract_id = (string) $contract->id;
            } elseif (array_key_exists('contract_id', $input) && $input['contract_id'] === null) {
                $po->contract_id = null;
            }

            $po->fill([
                'vatable_amount' => $totals['vatable_amount'],
                'vat_amount' => $totals['vat_amount'],
                'total_vat_inclusive' => $totals['total_vat_inclusive'],
                'grand_total' => $totals['grand_total'],
                'total_amount' => $totals['total_amount'],
            ]);

            $po->save();
            $this->mapper->syncLines($po, $totals['lines']);
            $this->persistComposeFormValues($po, $composeFormValues);

            foreach ($allocations as $prId => $amount) {
                $pr = collect($prs)->firstWhere('id', $prId);
                if ($pr instanceof ProcurementPr) {
                    $this->balances->syncPrLink($po, $pr, $amount);
                }
            }

            $this->balances->refreshPurchaseRequisitionStatuses($po);

            $this->bridge->syncDraft($po->refresh()->load(['lines', 'prLinks.purchaseRequisition']), $actor);

            $this->contractSpend->refreshForPurchaseOrder($po);

            return $this->registry->find((string) $po->id) ?? $po;
        });
    }

    public function transitionStatus(ProcurementPo $po, string $status, TenantUser $actor): ProcurementPo
    {
        if ($status === ProcurementPoStatus::SENT) {
            return app(ProcurementDocumentLifecycleService::class)->markPurchaseOrderSent($po, $actor);
        }

        abort_unless($actor->can('procurement_one:documents:manage'), 403);

        $allowed = match ((string) $po->status) {
            ProcurementPoStatus::SENT => [ProcurementPoStatus::PARTIALLY_RECEIVED, ProcurementPoStatus::RECEIVED],
            ProcurementPoStatus::PARTIALLY_RECEIVED => [ProcurementPoStatus::RECEIVED, ProcurementPoStatus::CLOSED],
            ProcurementPoStatus::RECEIVED => [ProcurementPoStatus::CLOSED],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => [__('Invalid purchase order status transition.')],
            ]);
        }

        $po->status = $status;
        if ($status === ProcurementPoStatus::SENT && $po->sent_at === null) {
            $po->sent_at = now();
        }

        $po->save();

        return $this->registry->find((string) $po->id) ?? $po;
    }

    /**
     * @param  list<mixed>  $lines
     * @param  list<ProcurementPr>  $prs
     * @return list<array<string, mixed>>
     */
    private function normalizeLines(array $lines, array $prs): array
    {
        $normalized = [];
        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $description = trim((string) ($line['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $normalized[] = [
                'item' => $line['item'] ?? null,
                'description' => $description,
                'uom' => $line['uom'] ?? 'EA',
                'quantity' => (float) ($line['quantity'] ?? 1),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'discount' => (float) ($line['discount'] ?? 0),
                'pr_id' => $line['pr_id'] ?? null,
                'pr_line_id' => $line['pr_line_id'] ?? null,
                'line_order' => $index,
            ];
        }

        if ($normalized === [] && count($prs) === 1) {
            foreach ($prs[0]->lines as $index => $prLine) {
                $normalized[] = [
                    'item' => null,
                    'description' => $prLine->description,
                    'uom' => 'EA',
                    'quantity' => (float) $prLine->quantity,
                    'unit_price' => (float) $prLine->unit_price,
                    'discount' => 0,
                    'pr_id' => (string) $prs[0]->id,
                    'pr_line_id' => (string) $prLine->id,
                    'line_order' => $index,
                ];
            }
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'lines' => [__('At least one line item is required.')],
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<ProcurementPr>  $prs
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array{pr_id: string, amount: float|int|string}>|null  $explicit
     * @return array<string, float>
     */
    private function resolveAllocations(array $prs, float $vatableTotal, array $lines, ?array $explicit): array
    {
        if (is_array($explicit) && $explicit !== []) {
            $allocations = [];
            foreach ($explicit as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $prId = (string) ($row['pr_id'] ?? '');
                if ($prId === '') {
                    continue;
                }
                $allocations[$prId] = round((float) ($row['amount'] ?? 0), 2);
            }

            $sum = round(array_sum($allocations), 2);
            if (abs($sum - $vatableTotal) > 0.01) {
                throw ValidationException::withMessages([
                    'allocations' => [__('PR allocations must equal the PO vatable amount.')],
                ]);
            }

            return $allocations;
        }

        if (count($prs) === 1) {
            return [(string) $prs[0]->id => round($vatableTotal, 2)];
        }

        $allocations = [];
        foreach ($lines as $line) {
            $prId = (string) ($line['pr_id'] ?? '');
            if ($prId === '') {
                throw ValidationException::withMessages([
                    'lines' => [__('Each line must reference a purchase requisition on consolidated POs.')],
                ]);
            }
            $qty = (float) ($line['quantity'] ?? 1);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $discount = (float) ($line['discount'] ?? 0);
            $amount = max(0, round(($qty * $unitPrice) - $discount, 2));
            $allocations[$prId] = round(($allocations[$prId] ?? 0) + $amount, 2);
        }

        $sum = round(array_sum($allocations), 2);
        if (abs($sum - $vatableTotal) > 0.01) {
            throw ValidationException::withMessages([
                'allocations' => [__('Line totals must match the PO vatable amount.')],
            ]);
        }

        return $allocations;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveContractForPurchaseOrder(array $input, float $grandTotal, ?ProcurementPo $existing = null): ?ProcurementContract
    {
        $contractId = array_key_exists('contract_id', $input)
            ? ($input['contract_id'] === null ? null : (string) $input['contract_id'])
            : ($existing?->contract_id !== null ? (string) $existing->contract_id : null);

        if ($contractId === null || $contractId === '') {
            return null;
        }

        $contract = $this->contractSpend->resolveActiveContract($contractId);
        $vendorCode = array_key_exists('vendor_code', $input)
            ? ($input['vendor_code'] !== null ? (string) $input['vendor_code'] : null)
            : ($existing?->vendor_code);
        $vendorName = array_key_exists('vendor_name', $input)
            ? ($input['vendor_name'] !== null ? (string) $input['vendor_name'] : null)
            : ($existing?->vendor_name);

        $this->contractSpend->assertVendorMatches($contract, $vendorCode, $vendorName);
        $this->contractSpend->evaluate(
            $contract,
            $grandTotal,
            $existing !== null ? (string) $existing->id : null,
        );

        return $contract;
    }

    /**
     * @param  array<string, mixed>|null  $composeFormValues
     */
    private function persistComposeFormValues(ProcurementPo $po, ?array $composeFormValues): void
    {
        if ($composeFormValues === null) {
            return;
        }

        $po->metadata_json = ProcurementComposeMetadata::withComposeFormValues(
            is_array($po->metadata_json) ? $po->metadata_json : [],
            $composeFormValues,
        );
        $po->save();
    }

    /**
     * @param  list<ProcurementPr>  $prs
     */
    private function primaryParentSubmissionId(array $prs): ?string
    {
        foreach ($prs as $pr) {
            if ($pr->e_approval_submission_id !== null && $pr->e_approval_submission_id !== '') {
                return (string) $pr->e_approval_submission_id;
            }
        }

        return null;
    }
}
