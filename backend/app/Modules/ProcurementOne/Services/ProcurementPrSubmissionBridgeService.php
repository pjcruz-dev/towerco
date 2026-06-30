<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementComposeMetadata;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementLineGridColumns;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use Illuminate\Support\Facades\DB;

final class ProcurementPrSubmissionBridgeService
{
    public function __construct(
        private readonly ProcurementPrFormResolverService $formResolver,
        private readonly ProcurementPrValueMapper $mapper,
        private readonly ProcurementFormValuesEApprovalMerger $valueMerger,
        private readonly ProcurementComposeSubmitValidator $submitValidator,
        private readonly EApprovalSubmissionService $submissions,
        private readonly ProcurementPrBudgetPolicyService $budgetPolicy,
        private readonly ProcurementDocumentEventDispatcher $events,
    ) {}

    public function ensureDraftSubmission(ProcurementPr $pr, TenantUser $actor): ProcurementPr
    {
        if ($pr->e_approval_submission_id !== null) {
            return $pr;
        }

        $form = $this->formResolver->resolvePublishedFormOrFail();
        $lines = $pr->lines()->get()->map(static fn ($line) => ProcurementLineGridColumns::prLineArray($line))->all();

        $submission = $this->submissions->createDraft(
            (string) $form->id,
            $this->eApprovalValues($pr, $lines),
            $actor,
            null,
            true,
            true,
        );

        $pr->e_approval_submission_id = (string) $submission->id;
        $pr->e_approval_form_id = (string) $form->id;
        $pr->save();

        return $pr->refresh();
    }

    public function syncDraft(ProcurementPr $pr, TenantUser $actor): ProcurementPr
    {
        $pr = $this->ensureDraftSubmission($pr, $actor);

        $submission = EApprovalSubmission::query()->findOrFail($pr->e_approval_submission_id);
        $lines = $pr->lines()->get()->map(static fn ($line) => ProcurementLineGridColumns::prLineArray($line))->all();

        $this->submissions->updateDraft(
            $submission,
            $this->eApprovalValues($pr, $lines),
            $actor,
        );

        return $pr->refresh();
    }

    /**
     * @return array{pr: ProcurementPr, warning: string|null}
     */
    public function submit(ProcurementPr $pr, TenantUser $actor): array
    {
        abort_unless(ProcurementPrStatus::isEditable((string) $pr->status), 422, __('Only draft purchase requisitions can be submitted.'));

        $evaluation = $this->budgetPolicy->evaluate($pr);
        $pr = $this->ensureDraftSubmission($pr, $actor);
        $this->submitValidator->assertPurchaseRequisitionSubmittable($pr);

        $submission = EApprovalSubmission::query()->findOrFail($pr->e_approval_submission_id);
        $lines = $pr->lines()->get()->map(static fn ($line) => ProcurementLineGridColumns::prLineArray($line))->all();

        $submitted = $this->submissions->submitDraft(
            $submission,
            $this->eApprovalValues($pr, $lines),
            $actor,
        );

        return DB::connection('tenant')->transaction(function () use ($pr, $submitted, $actor, $evaluation): array {
            $pr->document_no = $submitted->document_no;
            $pr->status = ProcurementPrStatus::PENDING_APPROVAL;
            $pr->submitted_at = now();
            $pr->save();

            $this->events->submitted(
                ProcurementDocumentType::PURCHASE_REQUISITION,
                (string) $pr->id,
                $pr->document_no,
                (string) $actor->id,
                ['e_approval_submission_id' => $submitted->id],
            );

            return ['pr' => $pr->refresh()->load(['lines', 'attachments', 'requestor']), 'warning' => $evaluation['warning']];
        });
    }

    public function cancel(ProcurementPr $pr, TenantUser $actor): ProcurementPr
    {
        if ($pr->e_approval_submission_id !== null) {
            $submission = EApprovalSubmission::query()->find($pr->e_approval_submission_id);
            if ($submission !== null) {
                $this->submissions->cancel($submission, $actor);
            }
        }

        $pr->status = ProcurementPrStatus::CANCELLED;
        $pr->cancelled_at = now();
        $pr->save();

        $this->events->cancelled(
            ProcurementDocumentType::PURCHASE_REQUISITION,
            (string) $pr->id,
            $pr->document_no,
            (string) $actor->id,
        );

        return $pr->refresh();
    }

    /**
     * @param  list<array{description: string, quantity: float, unit_price: float}>  $lines
     * @return array<string, mixed>
     */
    private function eApprovalValues(ProcurementPr $pr, array $lines): array
    {
        $base = $this->mapper->toEApprovalValues($pr, $lines);
        $metadata = is_array($pr->metadata_json) ? $pr->metadata_json : [];
        $compose = is_array($metadata[ProcurementComposeMetadata::COMPOSE_FORM_VALUES_KEY] ?? null)
            ? $metadata[ProcurementComposeMetadata::COMPOSE_FORM_VALUES_KEY]
            : [];

        return $this->valueMerger->mergePurchaseRequisition($base, $compose);
    }
}
