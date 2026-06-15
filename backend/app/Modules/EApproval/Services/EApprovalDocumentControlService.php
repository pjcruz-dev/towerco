<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalDocumentControlGate;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Illuminate\Support\Str;

final class EApprovalDocumentControlService
{
    public function __construct(
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalNotificationDispatcher $mail,
    ) {}

    public function parseGateForForm(EApprovalForm $form): ?EApprovalDocumentControlGate
    {
        $meta = $form->metadata_json;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : null;
        }

        return EApprovalDocumentControlGate::parse(is_array($meta) ? $meta : null);
    }

    public function tryEnterGate(EApprovalSubmission $submission, int $completedStepOrder): bool
    {
        $submission->loadMissing(['form', 'values.field']);
        if ($submission->status !== EApprovalSubmissionStatus::PENDING) {
            return false;
        }

        $gate = $this->parseGateForForm($submission->form);
        if ($gate === null || $gate->afterStepOrder !== $completedStepOrder) {
            return false;
        }

        $values = $this->valuesMap($submission);
        $prevLabel = $gate->resolvePreviousRevisionLabel($values, $submission->document_no);
        $nextRev = EApprovalDocumentControlGate::bumpRevisionCode($prevLabel);
        $titleTarget = trim($values[$gate->documentTitleField] ?? '');
        $titleSrc = $gate->documentTitleSourceField;
        $docTitle = $titleTarget !== '' ? $titleTarget : ($titleSrc ? trim($values[$titleSrc] ?? '') : '');

        $writes = [
            $gate->documentTitleField => $docTitle,
            $gate->previousRevisionField => $prevLabel,
            $gate->currentRevisionField => $nextRev !== '' ? $nextRev : $prevLabel,
            $gate->detailsField => '',
            $gate->reasonField => '',
        ];

        $form = $submission->form;
        if ($form !== null) {
            $fieldsByName = $form->fields()->get()->keyBy('name');
            foreach ($writes as $fieldName => $value) {
                $field = $fieldsByName->get($fieldName);
                if ($field === null) {
                    continue;
                }
                EApprovalFormValue::query()
                    ->where('submission_id', $submission->id)
                    ->where('field_id', $field->id)
                    ->delete();
                EApprovalFormValue::query()->create([
                    'id' => (string) Str::uuid(),
                    'submission_id' => $submission->id,
                    'field_id' => $field->id,
                    'value' => $value,
                ]);
            }
        }

        $submission->status = EApprovalSubmissionStatus::AWAITING_DCF;
        $submission->current_step = $completedStepOrder;
        $submission->save();

        $this->audit->log('document_control_gate_entered', $submission->id, "After step {$completedStepOrder}");
        $this->inApp->notify(
            (string) $submission->requestor_id,
            'awaiting_dcf',
            $submission->id,
            __('Document control is required for :doc.', ['doc' => $submission->document_no]),
            submission: $submission,
        );
        $this->mail->dispatchToRequestor($submission, 'awaiting_dcf', 'System');

        return true;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function assertConfiguredForDcfResubmit(EApprovalForm $form): EApprovalDocumentControlGate
    {
        $gate = $this->parseGateForForm($form);
        if ($gate === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'form' => [__('This form is not configured for document control gating.')],
            ]);
        }

        return $gate;
    }

    /**
     * @return array<string, string>
     */
    private function valuesMap(EApprovalSubmission $submission): array
    {
        $map = [];
        foreach ($submission->values as $row) {
            $key = $row->field?->name ?? (string) $row->field_id;
            $map[$key] = (string) ($row->value ?? '');
        }

        return $map;
    }
}
