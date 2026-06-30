<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementDocumentLifecycleService
{
    public function __construct(
        private readonly EApprovalSubmissionService $submissions,
        private readonly ProcurementDocumentEventDispatcher $events,
        private readonly ProcurementLifecycleAuditService $audit,
        private readonly ProcurementPoPrBalanceService $balances,
        private readonly ProcurementContractSpendService $contractSpend,
        private readonly ProcurementPoVendorNotificationService $vendorMail,
        private readonly ProcurementPrRegistryService $prRegistry,
        private readonly ProcurementPoRegistryService $poRegistry,
    ) {}

    public function cancelPurchaseRequisition(ProcurementPr $pr, TenantUser $actor, ?string $reason = null): ProcurementPr
    {
        if (! in_array((string) $pr->status, [ProcurementPrStatus::DRAFT, ProcurementPrStatus::PENDING_APPROVAL], true)) {
            throw ValidationException::withMessages([
                'status' => [__('Only draft or pending purchase requisitions can be cancelled.')],
            ]);
        }

        $reason = $this->normalizeReason($reason, required: (string) $pr->status === ProcurementPrStatus::PENDING_APPROVAL);

        return DB::connection('tenant')->transaction(function () use ($pr, $actor, $reason): ProcurementPr {
            if ($pr->e_approval_submission_id !== null) {
                $submission = EApprovalSubmission::query()->find($pr->e_approval_submission_id);
                if ($submission !== null) {
                    $this->submissions->cancel($submission, $actor);
                }
            }

            $pr->status = ProcurementPrStatus::CANCELLED;
            $pr->cancelled_at = now();
            $pr->lifecycle_reason = $reason;
            $pr->save();

            $this->audit->record(
                ProcurementDocumentType::PURCHASE_REQUISITION,
                (string) $pr->id,
                $pr->document_no,
                'cancelled',
                $actor,
                $reason,
            );

            $this->events->cancelled(
                ProcurementDocumentType::PURCHASE_REQUISITION,
                (string) $pr->id,
                $pr->document_no,
                (string) $actor->id,
                ['reason' => $reason],
            );

            return $this->prRegistry->find((string) $pr->id) ?? $pr->refresh();
        });
    }

    public function voidPurchaseRequisition(ProcurementPr $pr, TenantUser $actor, string $reason): ProcurementPr
    {
        abort_unless($actor->can('procurement_one:documents:manage'), 403);

        if (! in_array((string) $pr->status, [ProcurementPrStatus::APPROVED, ProcurementPrStatus::CONVERTED], true)) {
            throw ValidationException::withMessages([
                'status' => [__('Only approved purchase requisitions can be voided.')],
            ]);
        }

        if ($this->audit->hasActivePurchaseOrderCommitments((string) $pr->id)) {
            throw ValidationException::withMessages([
                'pr' => [__('Void the linked purchase orders before voiding this purchase requisition.')],
            ]);
        }

        $reason = $this->normalizeReason($reason, required: true);

        return DB::connection('tenant')->transaction(function () use ($pr, $actor, $reason): ProcurementPr {
            $pr->status = ProcurementPrStatus::VOIDED;
            $pr->voided_at = now();
            $pr->void_reason = $reason;
            $pr->voided_by_id = (string) $actor->id;
            $pr->lifecycle_reason = $reason;
            $pr->committed_po_amount = 0;
            $pr->save();

            $this->audit->record(
                ProcurementDocumentType::PURCHASE_REQUISITION,
                (string) $pr->id,
                $pr->document_no,
                'voided',
                $actor,
                $reason,
            );

            $this->events->voided(
                ProcurementDocumentType::PURCHASE_REQUISITION,
                (string) $pr->id,
                $pr->document_no,
                (string) $actor->id,
                ['reason' => $reason],
            );

            return $this->prRegistry->find((string) $pr->id) ?? $pr->refresh();
        });
    }

    public function cancelPurchaseOrder(ProcurementPo $po, TenantUser $actor, ?string $reason = null): ProcurementPo
    {
        if (! in_array((string) $po->status, [ProcurementPoStatus::DRAFT, ProcurementPoStatus::PENDING_APPROVAL], true)) {
            throw ValidationException::withMessages([
                'status' => [__('Only draft or pending purchase orders can be cancelled.')],
            ]);
        }

        $reason = $this->normalizeReason($reason, required: (string) $po->status === ProcurementPoStatus::PENDING_APPROVAL);

        return DB::connection('tenant')->transaction(function () use ($po, $actor, $reason): ProcurementPo {
            if ($po->e_approval_submission_id !== null) {
                $submission = EApprovalSubmission::query()->find($po->e_approval_submission_id);
                if ($submission !== null) {
                    $this->submissions->cancel($submission, $actor);
                }
            }

            $po->status = ProcurementPoStatus::CANCELLED;
            $po->cancelled_at = now();
            $po->lifecycle_reason = $reason;
            $po->save();

            $this->balances->refreshPurchaseRequisitionStatuses($po);
            $this->contractSpend->refreshForPurchaseOrder($po);

            $this->audit->record(
                ProcurementDocumentType::PURCHASE_ORDER,
                (string) $po->id,
                $po->document_no,
                'cancelled',
                $actor,
                $reason,
            );

            $this->events->cancelled(
                ProcurementDocumentType::PURCHASE_ORDER,
                (string) $po->id,
                $po->document_no,
                (string) $actor->id,
                ['reason' => $reason],
            );

            $this->vendorMail->dispatchForEvent($po->refresh(), 'po_cancelled', $actor, $reason);

            return $this->poRegistry->find((string) $po->id) ?? $po->refresh();
        });
    }

    public function voidPurchaseOrder(ProcurementPo $po, TenantUser $actor, string $reason): ProcurementPo
    {
        abort_unless($actor->can('procurement_one:documents:manage'), 403);

        if (! in_array((string) $po->status, [ProcurementPoStatus::APPROVED, ProcurementPoStatus::SENT], true)) {
            throw ValidationException::withMessages([
                'status' => [__('Only approved or sent purchase orders can be voided.')],
            ]);
        }

        $reason = $this->normalizeReason($reason, required: true);

        return DB::connection('tenant')->transaction(function () use ($po, $actor, $reason): ProcurementPo {
            $po->status = ProcurementPoStatus::VOIDED;
            $po->voided_at = now();
            $po->void_reason = $reason;
            $po->voided_by_id = (string) $actor->id;
            $po->lifecycle_reason = $reason;
            $po->save();

            $this->balances->refreshPurchaseRequisitionStatuses($po);
            $this->contractSpend->refreshForPurchaseOrder($po);

            $this->audit->record(
                ProcurementDocumentType::PURCHASE_ORDER,
                (string) $po->id,
                $po->document_no,
                'voided',
                $actor,
                $reason,
            );

            $this->events->voided(
                ProcurementDocumentType::PURCHASE_ORDER,
                (string) $po->id,
                $po->document_no,
                (string) $actor->id,
                ['reason' => $reason],
            );

            $this->vendorMail->dispatchForEvent($po->refresh(), 'po_voided', $actor, $reason);

            return $this->poRegistry->find((string) $po->id) ?? $po->refresh();
        });
    }

    public function markPurchaseOrderSent(ProcurementPo $po, TenantUser $actor): ProcurementPo
    {
        abort_unless($actor->can('procurement_one:documents:manage'), 403);

        if ((string) $po->status !== ProcurementPoStatus::APPROVED) {
            throw ValidationException::withMessages([
                'status' => [__('Only approved purchase orders can be marked as sent.')],
            ]);
        }

        $po->status = ProcurementPoStatus::SENT;
        if ($po->sent_at === null) {
            $po->sent_at = now();
        }
        $po->save();

        $this->audit->record(
            ProcurementDocumentType::PURCHASE_ORDER,
            (string) $po->id,
            $po->document_no,
            'marked_sent',
            $actor,
        );

        $this->vendorMail->maybeAutoDispatch($po->refresh(), 'po_sent', $actor);

        return $this->poRegistry->find((string) $po->id) ?? $po->refresh();
    }

    private function normalizeReason(?string $reason, bool $required): string
    {
        $normalized = trim((string) $reason);
        if ($required && mb_strlen($normalized) < 3) {
            throw ValidationException::withMessages([
                'reason' => [__('A reason of at least 3 characters is required.')],
            ]);
        }

        return $normalized;
    }
}
