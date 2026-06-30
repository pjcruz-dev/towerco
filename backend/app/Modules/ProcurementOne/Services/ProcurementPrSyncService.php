<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalPurchaseRequisitionService;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProcurementPrSyncService
{
    public function __construct(
        private readonly ProcurementPrValueMapper $mapper,
        private readonly ProcurementPrFormResolverService $formResolver,
        private readonly EApprovalPurchaseRequisitionService $purchaseRequisitions,
        private readonly ProcurementPoPrBalanceService $balances,
        private readonly ProcurementDocumentEventDispatcher $events,
    ) {}

    public function syncFromSubmission(EApprovalSubmission $submission, ?TenantUser $actor = null): ?ProcurementPr
    {
        $submission->loadMissing(['form', 'values.field', 'requestor']);

        if (! $this->isPurchaseRequisitionSubmission($submission)) {
            return null;
        }

        $existing = ProcurementPr::query()
            ->where('e_approval_submission_id', (string) $submission->id)
            ->first();

        if ($existing !== null && in_array((string) $existing->status, [
            ProcurementPrStatus::VOIDED,
            ProcurementPrStatus::CANCELLED,
        ], true)) {
            return $existing->load(['lines', 'attachments', 'requestor']);
        }

        return DB::connection('tenant')->transaction(function () use ($submission, $actor): ProcurementPr {
            $values = $this->submissionValues($submission);
            $lines = $this->mapper->linesFromGridValue($values['line_items'] ?? null);
            $hasPoChild = $this->hasPurchaseOrderChild($submission);
            $status = ProcurementPrStatus::fromEApprovalStatus((string) $submission->status, $hasPoChild);

            $pr = ProcurementPr::query()->firstOrNew([
                'e_approval_submission_id' => (string) $submission->id,
            ]);

            $lineTotal = $this->mapper->recalculateTotal($lines);
            $fieldTotal = isset($values['estimated_total']) && is_numeric($values['estimated_total'])
                ? (float) $values['estimated_total']
                : 0.0;
            $estimatedTotal = $lineTotal > 0
                ? $lineTotal
                : ($fieldTotal > 0 ? $fieldTotal : (float) ($pr->estimated_total ?? 0));

            $wasApproved = $pr->exists && $pr->status === ProcurementPrStatus::APPROVED;
            $previousStatus = $pr->exists ? (string) $pr->status : null;

            if (! $pr->exists) {
                $pr->id = (string) Str::uuid();
                $pr->requestor_id = (string) $submission->requestor_id;
            }

            $pr->fill([
                'document_no' => $submission->document_no,
                'status' => $status,
                'e_approval_form_id' => (string) $submission->form_id,
                'title' => trim((string) ($values['requisition_title'] ?? $submission->document_no)),
                'department' => $values['department'] ?? null,
                'urgency' => $values['urgency'] ?? null,
                'justification' => $values['justification'] ?? null,
                'estimated_total' => $estimatedTotal,
                'project_id' => $values['project_id'] ?? $pr->project_id,
                'rollout_id' => $values['rollout_id'] ?? $pr->rollout_id,
                'site_id' => $values['site_id'] ?? $pr->site_id,
                'boq_line_id' => $values['boq_line_id'] ?? $pr->boq_line_id,
            ]);

            if ($submission->status === EApprovalSubmissionStatus::PENDING && $pr->submitted_at === null) {
                $pr->submitted_at = $submission->created_at;
            }

            if ($status === ProcurementPrStatus::APPROVED || $status === ProcurementPrStatus::CONVERTED) {
                $pr->approved_at = $pr->approved_at ?? now();
            }

            if ($status === ProcurementPrStatus::REJECTED) {
                $pr->rejected_at = now();
            }

            if ($status === ProcurementPrStatus::CANCELLED) {
                $pr->cancelled_at = now();
            }

            if ($status === ProcurementPrStatus::VOIDED) {
                $pr->voided_at = $pr->voided_at ?? now();
            }

            $openBalance = $this->purchaseRequisitions->openBalanceForParent((string) $submission->id);
            if ($pr->exists) {
                $pr->committed_po_amount = round($this->balances->committedForPr($pr), 2);
            } elseif ($openBalance !== null) {
                $estimated = (float) $pr->estimated_total;
                $pr->committed_po_amount = max(0, round($estimated - $openBalance, 2));
            }

            $pr->save();
            $this->mapper->syncLines($pr, $lines);

            $this->dispatchLifecycleEvents($pr, $previousStatus, $wasApproved, $actor);

            return $pr->refresh()->load(['lines', 'attachments', 'requestor']);
        });
    }

    private function dispatchLifecycleEvents(
        ProcurementPr $pr,
        ?string $previousStatus,
        bool $wasApproved,
        ?TenantUser $actor,
    ): void {
        $actorId = $actor?->id;

        if ($pr->status === ProcurementPrStatus::APPROVED && ! $wasApproved && $previousStatus !== ProcurementPrStatus::CONVERTED) {
            $this->events->approved(
                ProcurementDocumentType::PURCHASE_REQUISITION,
                (string) $pr->id,
                $pr->document_no,
                $actorId !== null ? (string) $actorId : null,
                ['e_approval_submission_id' => $pr->e_approval_submission_id],
            );
        }

        if ($pr->status === ProcurementPrStatus::CANCELLED && $previousStatus !== ProcurementPrStatus::CANCELLED) {
            $this->events->cancelled(
                ProcurementDocumentType::PURCHASE_REQUISITION,
                (string) $pr->id,
                $pr->document_no,
                $actorId !== null ? (string) $actorId : null,
            );
        }

        if ($pr->status === ProcurementPrStatus::VOIDED && $previousStatus !== ProcurementPrStatus::VOIDED) {
            $this->events->voided(
                ProcurementDocumentType::PURCHASE_REQUISITION,
                (string) $pr->id,
                $pr->document_no,
                $actorId !== null ? (string) $actorId : null,
            );
        }
    }

    private function isPurchaseRequisitionSubmission(EApprovalSubmission $submission): bool
    {
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];

        return ($metadata['form_family'] ?? null) === 'purchase_requisition';
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

    private function hasPurchaseOrderChild(EApprovalSubmission $submission): bool
    {
        return EApprovalSubmission::query()
            ->where('parent_submission_id', $submission->id)
            ->where('status', '<>', EApprovalSubmissionStatus::REJECTED)
            ->with('form')
            ->get()
            ->contains(static function (EApprovalSubmission $child): bool {
                $metadata = is_array($child->form?->metadata_json) ? $child->form->metadata_json : [];

                return ($metadata['form_family'] ?? null) === 'purchase_order';
            });
    }
}
