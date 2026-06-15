<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;

final class EApprovalSubmissionPrintService
{
    public function __construct(
        private readonly EApprovalPdfLayoutService $pdfLayout,
        private readonly EApprovalSettingsService $settings,
        private readonly EApprovalFormValueDisplayService $valueDisplay,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPrintModel(EApprovalSubmission $submission): array
    {
        $submission->loadMissing(['form', 'requestor', 'values.field', 'approvals.approver', 'approvals.step', 'attachments']);

        $layoutPayload = $this->pdfLayout->show((string) $submission->form_id);
        $layoutRows = is_array($layoutPayload['layout'] ?? null) ? $layoutPayload['layout'] : [];
        $template = is_array($layoutPayload['template'] ?? null) ? $layoutPayload['template'] : [];
        $layoutPersisted = (bool) ($layoutPayload['layout_persisted'] ?? false);

        $usersById = $this->valueDisplay->approverUsersById($submission->values);

        $valuesByKey = [];
        foreach ($submission->values as $row) {
            $key = $row->field?->name ?? (string) $row->field_id;
            $type = $row->field?->type;
            $raw = $row->value;
            $options = $row->field?->options;
            $valuesByKey[$key] = [
                'label' => $row->field?->label ?? $key,
                'value' => $this->valueDisplay->resolveDisplayValue(
                    $type,
                    $raw,
                    $usersById,
                    is_array($options) ? $options : null,
                ),
            ];
        }

        $fields = $this->resolvePrintFields($valuesByKey, $layoutRows, $layoutPersisted);

        return [
            'document_no' => $submission->document_no,
            'form_name' => $submission->form?->name,
            'status' => $submission->status,
            'requestor' => $submission->requestor?->name,
            'requestor_signature' => $submission->requestor_id
                ? $this->settings->getUserSignature((string) $submission->requestor_id)
                : null,
            'created_at' => $submission->created_at?->format('Y-m-d H:i'),
            'fields' => $fields,
            'approvals' => $submission->approvals->map(static fn ($a) => [
                'step' => $a->step?->step_order,
                'approver' => $a->approver?->name,
                'status' => $a->status,
                'remarks' => $a->remarks,
                'signature' => $a->signature,
                'acted_at' => $a->acted_at?->format('Y-m-d H:i'),
            ])->values()->all(),
            'attachments' => $submission->attachments->map(static fn ($a) => [
                'id' => (string) $a->id,
                'file_name' => $a->file_name,
                'field_name' => $a->field_name,
            ])->values()->all(),
            'template' => $template,
            'show_approval_trail' => count($submission->approvals) > 0,
        ];
    }

    /**
     * @param  array<string, array{label: string, value: string|null}>  $valuesByKey
     * @param  list<mixed>  $layoutRows
     * @return list<array{key: string, label: string, value: string|null}>
     */
    private function resolvePrintFields(array $valuesByKey, array $layoutRows, bool $layoutPersisted): array
    {
        if (! $layoutPersisted) {
            return array_map(
                static fn (array $row, string $key) => [
                    'key' => $key,
                    'label' => $row['label'],
                    'value' => $row['value'],
                ],
                $valuesByKey,
                array_keys($valuesByKey),
            );
        }

        $fields = [];
        foreach ($layoutRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (empty($row['visible'])) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! isset($valuesByKey[$key])) {
                continue;
            }
            $fields[] = [
                'key' => $key,
                'label' => (string) ($row['label'] ?? $valuesByKey[$key]['label']),
                'value' => $valuesByKey[$key]['value'],
            ];
        }

        if ($fields === []) {
            return array_map(
                static fn (array $row, string $key) => [
                    'key' => $key,
                    'label' => $row['label'],
                    'value' => $row['value'],
                ],
                $valuesByKey,
                array_keys($valuesByKey),
            );
        }

        return $fields;
    }
}
