<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementComposeMetadata;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use Illuminate\Support\Facades\DB;

final class ProcurementApInvoiceSubmissionBridgeService
{
    public function __construct(

        private readonly ProcurementApInvoiceFormResolverService $formResolver,

        private readonly ProcurementApInvoiceValueMapper $mapper,

        private readonly ProcurementFormValuesEApprovalMerger $valueMerger,

        private readonly ProcurementComposeSubmitValidator $submitValidator,

        private readonly EApprovalSubmissionService $submissions,

        private readonly ProcurementApInvoiceMatchingService $matching,

        private readonly ProcurementDocumentEventDispatcher $events,

    ) {}

    /**
     * @return array{invoice: ProcurementApInvoice, warning: string|null}
     */
    public function submit(ProcurementApInvoice $invoice, TenantUser $actor): array
    {

        abort_unless(ProcurementApInvoiceStatus::isEditable((string) $invoice->status), 422, __('Only draft AP invoices can be submitted.'));

        $evaluation = $this->matching->evaluate($invoice->refresh()->load('lines'));

        $invoice = $this->ensureDraftSubmission($invoice, $actor);

        $this->submitValidator->assertApInvoiceSubmittable($invoice);

        $submission = EApprovalSubmission::query()->findOrFail($invoice->e_approval_submission_id);

        $parentSubmissionId = $this->formResolver->parentSubmissionId($invoice);

        $poDocumentNo = $invoice->purchaseOrder?->document_no;

        $submitted = $this->submissions->submitDraft(

            $submission,

            $this->eApprovalValues($invoice, $poDocumentNo),

            $actor,

            $parentSubmissionId,

            true,

        );

        return DB::connection('tenant')->transaction(function () use ($invoice, $submitted, $actor, $evaluation): array {

            $invoice->document_no = $submitted->document_no;

            $invoice->status = ProcurementApInvoiceStatus::PENDING_APPROVAL;

            $invoice->submitted_at = now();

            $invoice->match_status = $evaluation['match_status'];

            $invoice->match_variance_amount = $evaluation['variance_amount'];

            $invoice->metadata_json = array_merge($invoice->metadata_json ?? [], ['match' => $evaluation]);

            $invoice->save();

            $this->events->submitted(

                ProcurementDocumentType::AP_INVOICE,

                (string) $invoice->id,

                $invoice->document_no,

                (string) $actor->id,

                ['e_approval_submission_id' => $submitted->id],

            );

            return [

                'invoice' => $invoice->refresh()->load(['lines', 'purchaseOrder', 'goodsReceipt', 'requestor']),

                'warning' => $evaluation['warning'],

            ];

        });

    }

    public function ensureDraftSubmission(ProcurementApInvoice $invoice, TenantUser $actor): ProcurementApInvoice
    {

        if ($invoice->e_approval_submission_id !== null) {

            return $invoice;

        }

        $form = $this->formResolver->resolvePublishedFormOrFail();

        $parentSubmissionId = $this->formResolver->parentSubmissionId($invoice);

        $poDocumentNo = $invoice->purchaseOrder?->document_no;

        $submission = $this->submissions->createDraft(

            (string) $form->id,

            $this->eApprovalValues($invoice, $poDocumentNo),

            $actor,

            $parentSubmissionId,

            true,

            true,

        );

        $invoice->e_approval_submission_id = (string) $submission->id;

        $invoice->e_approval_form_id = (string) $form->id;

        $invoice->save();

        return $invoice->refresh();

    }

    public function syncDraft(ProcurementApInvoice $invoice, TenantUser $actor): ProcurementApInvoice
    {

        $invoice = $this->ensureDraftSubmission($invoice, $actor);

        $submission = EApprovalSubmission::query()->findOrFail($invoice->e_approval_submission_id);

        $parentSubmissionId = $this->formResolver->parentSubmissionId($invoice);

        $poDocumentNo = $invoice->purchaseOrder?->document_no;

        $this->submissions->updateDraft(

            $submission,

            $this->eApprovalValues($invoice, $poDocumentNo),

            $actor,

            $parentSubmissionId,

            true,

        );

        return $invoice->refresh();

    }

    private function eApprovalValues(ProcurementApInvoice $invoice, ?string $poDocumentNo): array
    {

        $invoice->loadMissing('purchaseOrder');

        $base = $this->mapper->toEApprovalValues($invoice, $poDocumentNo);

        $compose = ProcurementComposeMetadata::composeFormValues(

            is_array($invoice->metadata_json) ? $invoice->metadata_json : null,

        );

        return $this->valueMerger->mergeApInvoice($base, $compose);

    }
}
