<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProcurementPoSyncService
{
    public function __construct(
        private readonly ProcurementPoValueMapper $mapper,
        private readonly ProcurementPoLineCalculator $calculator,
        private readonly ProcurementPoFormResolverService $formResolver,
        private readonly ProcurementPoPrBalanceService $balances,
        private readonly ProcurementDocumentEventDispatcher $events,
    ) {}

    public function syncFromSubmission(EApprovalSubmission $submission, ?TenantUser $actor = null): ?ProcurementPo
    {
        $submission->loadMissing(['form', 'values.field', 'requestor']);

        if (! $this->isPurchaseOrderSubmission($submission)) {
            return null;
        }

        $existing = ProcurementPo::query()
            ->where('e_approval_submission_id', (string) $submission->id)
            ->first();

        if ($existing !== null && in_array((string) $existing->status, [
            ProcurementPoStatus::VOIDED,
            ProcurementPoStatus::CANCELLED,
        ], true)) {
            return $existing->load(['lines', 'prLinks.purchaseRequisition', 'requestor']);
        }

        return DB::connection('tenant')->transaction(function () use ($submission, $actor): ProcurementPo {
            $values = $this->submissionValues($submission);
            $lines = $this->mapper->linesFromGridValue($values['line_items'] ?? null);
            $mappedStatus = ProcurementPoStatus::fromEApprovalStatus((string) $submission->status);

            $po = ProcurementPo::query()->firstOrNew([
                'e_approval_submission_id' => (string) $submission->id,
            ]);

            $wasApproved = $po->exists && $po->status === ProcurementPoStatus::APPROVED;
            $previousStatus = $po->exists ? (string) $po->status : null;

            if (! $po->exists) {
                $po->id = (string) Str::uuid();
                $po->requestor_id = (string) $submission->requestor_id;
            }

            $totals = $this->calculator->calculate(
                $lines,
                (float) ($values['vat_exempt_amount'] ?? 0),
                (float) ($values['zero_rated_amount'] ?? 0),
                (float) ($values['vat_rate'] ?? 12),
                (float) ($values['less_discount'] ?? 0),
            );

            $status = $this->resolveEntityStatus($po, $mappedStatus);

            $vatableAmount = $this->resolveNumericValue($values, 'vatable_amount', $totals['vatable_amount']);
            $vatAmount = $this->resolveNumericValue($values, 'vat_amount', $totals['vat_amount']);
            $totalVatInclusive = $this->resolveNumericValue($values, 'total_vat_inclusive', $totals['total_vat_inclusive']);
            $grandTotal = $this->resolveNumericValue($values, 'grand_total', $totals['grand_total']);
            if ($grandTotal <= 0) {
                $grandTotal = $this->resolveNumericValue($values, 'total_vat_inclusive', $totalVatInclusive);
            }
            if ($grandTotal <= 0) {
                $grandTotal = $this->resolveNumericValue($values, 'total_amount', $totals['total_amount']);
            }
            if ($grandTotal <= 0 && $po->exists && (float) $po->grand_total > 0) {
                $grandTotal = (float) $po->grand_total;
            }
            if ($grandTotal <= 0 && $totalVatInclusive > 0) {
                $grandTotal = $totalVatInclusive;
            }

            $totalAmount = $this->resolveNumericValue($values, 'total_amount', $totals['total_amount']);
            if ($totalAmount <= 0) {
                $totalAmount = $vatableAmount > 0 ? $vatableAmount : $grandTotal;
            }

            $po->fill([
                'document_no' => $submission->document_no,
                'status' => $status,
                'e_approval_form_id' => (string) $submission->form_id,
                'vendor_code' => $values['vendor'] ?? $po->vendor_code,
                'vendor_name' => $values['supplier'] ?? $po->vendor_name,
                'supplier' => $values['supplier'] ?? $po->supplier,
                'ship_to' => $values['ship_to'] ?? $po->ship_to,
                'delivery_date' => $values['delivery_date'] ?? $values['required_delivery_date'] ?? $po->delivery_date,
                'payment_terms' => $values['payment_terms'] ?? $po->payment_terms,
                'currency_code' => (string) ($values['currency_code'] ?? $po->currency_code ?? 'PHP'),
                'exchange_rate' => (float) ($values['exchange_rate'] ?? $po->exchange_rate ?? 1),
                'delivery_location' => $values['delivery_location'] ?? $po->delivery_location,
                'vatable_amount' => $vatableAmount,
                'vat_exempt_amount' => $this->resolveNumericValue($values, 'vat_exempt_amount', 0),
                'zero_rated_amount' => $this->resolveNumericValue($values, 'zero_rated_amount', 0),
                'vat_rate' => $this->resolveNumericValue($values, 'vat_rate', 12),
                'vat_amount' => $vatAmount > 0 ? $vatAmount : $totals['vat_amount'],
                'total_vat_inclusive' => $totalVatInclusive > 0 ? $totalVatInclusive : $totals['total_vat_inclusive'],
                'less_discount' => $this->resolveNumericValue($values, 'less_discount', 0),
                'grand_total' => $grandTotal,
                'total_amount' => $totalAmount,
            ]);

            if ($submission->status === EApprovalSubmissionStatus::PENDING && $po->submitted_at === null) {
                $po->submitted_at = $submission->created_at;
            }

            if ($status === ProcurementPoStatus::APPROVED) {
                $po->approved_at = $po->approved_at ?? now();
            }

            if ($status === ProcurementPoStatus::CANCELLED) {
                $po->cancelled_at = now();
            }

            if ($status === ProcurementPoStatus::VOIDED) {
                $po->voided_at = $po->voided_at ?? now();
            }

            $po->save();
            $this->mapper->syncLines($po, $lines);
            $this->syncPurchaseRequisitionLinks($po, $submission, (float) $po->vatable_amount);

            $this->dispatchLifecycleEvents($po, $previousStatus, $wasApproved, $actor);

            return $po->refresh()->load(['lines', 'prLinks.purchaseRequisition', 'requestor']);
        });
    }

    private function resolveEntityStatus(ProcurementPo $po, string $mappedStatus): string
    {
        $fulfillment = [
            ProcurementPoStatus::SENT,
            ProcurementPoStatus::PARTIALLY_RECEIVED,
            ProcurementPoStatus::RECEIVED,
            ProcurementPoStatus::CLOSED,
        ];

        if ($po->exists && in_array((string) $po->status, $fulfillment, true) && $mappedStatus === ProcurementPoStatus::APPROVED) {
            return (string) $po->status;
        }

        return $mappedStatus;
    }

    private function syncPurchaseRequisitionLinks(ProcurementPo $po, EApprovalSubmission $submission, float $allocatedBase): void
    {
        $linkedPrIds = [];
        if (is_array($po->metadata_json['linked_pr_ids'] ?? null)) {
            $linkedPrIds = array_map('strval', $po->metadata_json['linked_pr_ids']);
        }

        if ($submission->parent_submission_id !== null) {
            $parentPr = ProcurementPr::query()
                ->where('e_approval_submission_id', $submission->parent_submission_id)
                ->first();
            if ($parentPr !== null) {
                $linkedPrIds[] = (string) $parentPr->id;
            }
        }

        $linkedPrIds = array_values(array_unique(array_filter($linkedPrIds)));

        if ($linkedPrIds === []) {
            return;
        }

        if (count($linkedPrIds) === 1) {
            $pr = ProcurementPr::query()->find($linkedPrIds[0]);
            if ($pr instanceof ProcurementPr) {
                $this->balances->syncPrLink($po, $pr, $allocatedBase);
            }

            $this->balances->refreshPurchaseRequisitionStatuses($po);

            return;
        }

        $perPr = round($allocatedBase / count($linkedPrIds), 2);
        $remaining = $allocatedBase;
        foreach ($linkedPrIds as $index => $prId) {
            $pr = ProcurementPr::query()->find($prId);
            if (! $pr instanceof ProcurementPr) {
                continue;
            }
            $amount = $index === count($linkedPrIds) - 1 ? round($remaining, 2) : $perPr;
            $remaining -= $amount;
            $this->balances->syncPrLink($po, $pr, $amount);
        }

        $this->balances->refreshPurchaseRequisitionStatuses($po);
    }

    private function dispatchLifecycleEvents(
        ProcurementPo $po,
        ?string $previousStatus,
        bool $wasApproved,
        ?TenantUser $actor,
    ): void {
        $actorId = $actor?->id;

        if ($po->status === ProcurementPoStatus::APPROVED && ! $wasApproved) {
            $this->events->approved(
                ProcurementDocumentType::PURCHASE_ORDER,
                (string) $po->id,
                $po->document_no,
                $actorId !== null ? (string) $actorId : null,
                ['e_approval_submission_id' => $po->e_approval_submission_id],
            );
        }

        if ($po->status === ProcurementPoStatus::CANCELLED && $previousStatus !== ProcurementPoStatus::CANCELLED) {
            $this->events->cancelled(
                ProcurementDocumentType::PURCHASE_ORDER,
                (string) $po->id,
                $po->document_no,
                $actorId !== null ? (string) $actorId : null,
            );
            $this->balances->refreshPurchaseRequisitionStatuses($po);
        }

        if ($po->status === ProcurementPoStatus::VOIDED && $previousStatus !== ProcurementPoStatus::VOIDED) {
            $this->events->voided(
                ProcurementDocumentType::PURCHASE_ORDER,
                (string) $po->id,
                $po->document_no,
                $actorId !== null ? (string) $actorId : null,
            );
            $this->balances->refreshPurchaseRequisitionStatuses($po);
        }
    }

    private function isPurchaseOrderSubmission(EApprovalSubmission $submission): bool
    {
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];

        return ($metadata['form_family'] ?? null) === 'purchase_order';
    }

    /**
     * @return array<string, mixed>
     */
    private function submissionValues(EApprovalSubmission $submission): array
    {
        $values = [];
        foreach ($submission->values as $formValue) {
            $name = (string) ($formValue->field?->name ?? '');
            if ($name === '') {
                continue;
            }
            $values[$name] = $formValue->value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function resolveNumericValue(array $values, string $key, float $fallback): float
    {
        if (! array_key_exists($key, $values)) {
            return $fallback;
        }

        $raw = trim(str_replace(',', '', (string) $values[$key]));
        if ($raw === '' || ! is_numeric($raw)) {
            return $fallback;
        }

        return (float) $raw;
    }
}
