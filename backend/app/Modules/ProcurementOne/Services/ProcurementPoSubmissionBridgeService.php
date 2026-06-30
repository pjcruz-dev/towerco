<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Support\ProcurementComposeMetadata;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use Illuminate\Support\Facades\DB;

final class ProcurementPoSubmissionBridgeService
{
    public function __construct(

        private readonly ProcurementPoFormResolverService $formResolver,

        private readonly ProcurementPoValueMapper $mapper,

        private readonly ProcurementFormValuesEApprovalMerger $valueMerger,

        private readonly ProcurementComposeSubmitValidator $submitValidator,

        private readonly EApprovalSubmissionService $submissions,

        private readonly ProcurementDocumentEventDispatcher $events,

        private readonly ProcurementPoPrBalanceService $balances,

    ) {}

    public function ensureDraftSubmission(
        ProcurementPo $po,
        TenantUser $actor,
        ?string $parentSubmissionId = null,
        ?string $prDocumentNo = null,
    ): ProcurementPo {

        if ($po->e_approval_submission_id !== null) {

            return $po;

        }

        $form = $this->formResolver->resolvePublishedFormOrFail();

        $parentSubmissionId = $parentSubmissionId ?? $this->formResolver->primaryParentSubmissionId($po);

        $prDocumentNo = $prDocumentNo ?? $this->formResolver->primaryPrDocumentNo($po);

        $submission = $this->submissions->createDraft(

            (string) $form->id,

            $this->eApprovalValues($po, $prDocumentNo),

            $actor,

            $parentSubmissionId,

            true,

            true,

        );

        $po->e_approval_submission_id = (string) $submission->id;

        $po->e_approval_form_id = (string) $form->id;

        $po->save();

        return $po->refresh();

    }

    public function syncDraft(ProcurementPo $po, TenantUser $actor): ProcurementPo
    {

        $po = $this->ensureDraftSubmission($po, $actor);

        $submission = EApprovalSubmission::query()->findOrFail($po->e_approval_submission_id);

        $parentSubmissionId = $this->formResolver->primaryParentSubmissionId($po);

        $this->submissions->updateDraft(

            $submission,

            $this->eApprovalValues($po, $this->formResolver->primaryPrDocumentNo($po)),

            $actor,

            $parentSubmissionId,

            true,

        );

        return $po->refresh();

    }

    /**
     * @return array{po: ProcurementPo, warning: string|null}
     */
    public function submit(ProcurementPo $po, TenantUser $actor): array
    {

        abort_unless(ProcurementPoStatus::isEditable((string) $po->status), 422, __('Only draft purchase orders can be submitted.'));

        $po = $this->ensureDraftSubmission($po, $actor);

        $this->submitValidator->assertPurchaseOrderSubmittable($po);

        $submission = EApprovalSubmission::query()->findOrFail($po->e_approval_submission_id);

        $parentSubmissionId = $this->formResolver->primaryParentSubmissionId($po);

        $submitted = $this->submissions->submitDraft(

            $submission,

            $this->eApprovalValues($po, $this->formResolver->primaryPrDocumentNo($po)),

            $actor,

            $parentSubmissionId,

            true,

        );

        return DB::connection('tenant')->transaction(function () use ($po, $submitted, $actor): array {

            $po->document_no = $submitted->document_no;

            $po->status = ProcurementPoStatus::PENDING_APPROVAL;

            $po->submitted_at = now();

            $po->save();

            $this->balances->refreshPurchaseRequisitionStatuses($po);

            $this->events->submitted(

                ProcurementDocumentType::PURCHASE_ORDER,

                (string) $po->id,

                $po->document_no,

                (string) $actor->id,

                ['e_approval_submission_id' => $submitted->id],

            );

            return [

                'po' => $po->refresh()->load(['lines', 'prLinks.purchaseRequisition', 'requestor']),

                'warning' => null,

            ];

        });

    }

    public function cancel(ProcurementPo $po, TenantUser $actor): ProcurementPo
    {

        if ($po->e_approval_submission_id !== null) {

            $submission = EApprovalSubmission::query()->find($po->e_approval_submission_id);

            if ($submission !== null) {

                $this->submissions->cancel($submission, $actor);

            }

        }

        $po->status = ProcurementPoStatus::CANCELLED;

        $po->cancelled_at = now();

        $po->save();

        $this->balances->refreshPurchaseRequisitionStatuses($po);

        $this->events->cancelled(

            ProcurementDocumentType::PURCHASE_ORDER,

            (string) $po->id,

            $po->document_no,

            (string) $actor->id,

        );

        return $po->refresh();

    }

    private function eApprovalValues(ProcurementPo $po, ?string $prDocumentNo): array
    {

        $base = $this->mapper->toEApprovalValues($po, $prDocumentNo);

        $compose = ProcurementComposeMetadata::composeFormValues(

            is_array($po->metadata_json) ? $po->metadata_json : null,

        );

        return $this->valueMerger->mergePurchaseOrder($base, $compose);

    }
}
