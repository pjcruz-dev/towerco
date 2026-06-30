<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\ProcurementOne\Models\ProcurementPr;

final class ProcurementPrPrintEnrichmentService
{
    public function __construct(
        private readonly ProcurementPrValueMapper $mapper,
    ) {}

    /**
     * @param  array<string, array{label: string, value: string|null}>  $valuesByKey
     * @return array<string, array{label: string, value: string|null}>
     */
    public function enrichSubmissionPrintValues(EApprovalSubmission $submission, array $valuesByKey): array
    {
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        if (($metadata['form_family'] ?? null) !== 'purchase_requisition') {
            return $valuesByKey;
        }

        $pr = ProcurementPr::query()
            ->with('lines')
            ->where('e_approval_submission_id', (string) $submission->id)
            ->first();

        if ($pr === null) {
            return $valuesByKey;
        }

        $mapped = $this->mapper->toEApprovalValues($pr, $pr->lines->map(static fn ($line) => [
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'metadata_json' => $line->metadata_json,
        ])->all());

        $printFields = [
            'requisition_title' => $mapped['requisition_title'] ?? null,
            'department' => $mapped['department'] ?? null,
            'urgency' => $mapped['urgency'] ?? null,
            'estimated_total' => $mapped['estimated_total'] ?? null,
            'justification' => $mapped['justification'] ?? null,
            'project_id' => $mapped['project_id'] ?? null,
            'rollout_id' => $mapped['rollout_id'] ?? null,
            'site_id' => $mapped['site_id'] ?? null,
            'boq_line_id' => $mapped['boq_line_id'] ?? null,
        ];

        foreach ($printFields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $existing = trim((string) ($valuesByKey[$key]['value'] ?? ''));
            if ($existing !== '' && $existing !== '—') {
                continue;
            }

            $valuesByKey[$key] = [
                'label' => $valuesByKey[$key]['label'] ?? $this->labelForKey($key),
                'value' => (string) $value,
            ];
        }

        return $valuesByKey;
    }

    private function labelForKey(string $key): string
    {
        return match ($key) {
            'requisition_title' => 'Title / summary',
            'estimated_total' => 'Estimated total',
            'justification' => 'Business justification',
            'project_id' => 'Project ID',
            'rollout_id' => 'Rollout ID',
            'site_id' => 'Site ID',
            'boq_line_id' => 'BOQ line ID',
            default => $key,
        };
    }
}
