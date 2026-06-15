<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalFormImportExportService
{
    public function __construct(
        private readonly EApprovalFormService $forms,
        private readonly EApprovalFormValidator $validator,
        private readonly EApprovalAuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function exportEnvelope(EApprovalForm $form): array
    {
        $form->loadMissing(['fields', 'workflowTemplate.steps']);

        return [
            'format' => 'atc-form-export',
            'formatVersion' => 1,
            'exportedAt' => now()->toIso8601String(),
            'form' => $form->toDetailPayload(),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{form: EApprovalForm, warnings: list<string>}
     */
    public function import(array $body, TenantUser $actor): array
    {
        $checked = $this->validateImportPayload($body);
        if ($checked['ok'] === false) {
            throw ValidationException::withMessages(['import' => [$checked['error']]]);
        }

        $inner = $checked['inner'];
        $payload = $this->buildCreatePayloadFromImport($inner);
        $warnings = array_merge($checked['warnings'], $payload['warnings'] ?? []);
        unset($payload['warnings']);

        $result = $this->forms->create($payload, $actor);
        $this->audit->log('form_imported', $result['form']->id, $result['form']->name, $actor);

        return [
            'form' => $result['form'],
            'warnings' => array_values(array_unique(array_merge($warnings, $result['warnings']))),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: true, inner: array<string, mixed>, warnings: list<string>}|array{ok: false, error: string, warnings: list<string>}
     */
    public function validateImportPayload(array $body): array
    {
        $payload = $body['form'] ?? $body;
        if (isset($body['format']) && $body['format'] !== 'atc-form-export') {
            return ['ok' => false, 'error' => 'Unknown export format. Expected atc-form-export.', 'warnings' => []];
        }

        if (! is_array($payload)) {
            return ['ok' => false, 'error' => 'Invalid export: missing form object.', 'warnings' => []];
        }

        $inner = is_array($payload['form'] ?? null) ? $payload['form'] : $payload;

        if (trim((string) ($inner['name'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'Invalid export: form name is required.', 'warnings' => []];
        }

        if (! is_array($inner['fields'] ?? null) || count($inner['fields']) === 0) {
            return ['ok' => false, 'error' => 'Invalid export: fields must be a non-empty array.', 'warnings' => []];
        }

        try {
            $warnings = $this->validator->validate($inner, ($inner['status'] ?? 'draft') === 'published');
        } catch (ValidationException $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'warnings' => []];
        }

        return ['ok' => true, 'inner' => $inner, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $inner
     * @return array<string, mixed>&array{warnings: list<string>}
     */
    private function buildCreatePayloadFromImport(array $inner): array
    {
        $warnings = [];
        $restrictedTo = $inner['restricted_to'] ?? null;
        if (is_string($restrictedTo) && trim($restrictedTo) === '') {
            $restrictedTo = null;
        }

        $brandLogoUrl = $this->normalizeLegacyBrandLogoUrl($inner['brand_logo_url'] ?? null, $warnings);

        return [
            'name' => $inner['name'],
            'description' => $this->nullableString($inner['description'] ?? null),
            'category' => (string) ($inner['category'] ?? 'general'),
            'metadata_json' => is_array($inner['metadata_json'] ?? null) ? $inner['metadata_json'] : null,
            'restricted_to' => $restrictedTo,
            'status' => in_array($inner['status'] ?? 'draft', ['draft', 'published'], true)
                ? (string) $inner['status']
                : 'draft',
            'schema_version' => max(1, (int) ($inner['schema_version'] ?? 1)),
            'owner_code' => (string) ($inner['owner_code'] ?? 'GEN'),
            'doc_type_code' => (string) ($inner['doc_type_code'] ?? 'F'),
            'doc_no_custom_enabled' => (bool) ($inner['doc_no_custom_enabled'] ?? false),
            'doc_no_template' => $this->nullableString($inner['doc_no_template'] ?? null),
            'doc_no_seq_start' => is_numeric($inner['doc_no_seq_start'] ?? null)
                ? (int) $inner['doc_no_seq_start']
                : null,
            'doc_no_seq_start_rules' => is_array($inner['doc_no_seq_start_rules'] ?? null)
                ? $inner['doc_no_seq_start_rules']
                : null,
            'brand_logo_url' => $brandLogoUrl,
            'brand_primary_color' => $this->nullableString($inner['brand_primary_color'] ?? null),
            'related_form_ids' => is_array($inner['related_form_ids'] ?? null) ? $inner['related_form_ids'] : null,
            'fields' => $this->normalizeImportFields($inner['fields'] ?? []),
            'steps' => $this->normalizeImportSteps($inner['steps'] ?? []),
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<mixed>  $fields
     * @return list<array<string, mixed>>
     */
    private function normalizeImportFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                $name = 'field_'.($index + 1);
            }
            $normalized[] = [
                'type' => (string) ($field['type'] ?? 'text'),
                'name' => $name,
                'label' => (string) ($field['label'] ?? $name),
                'semantic_type' => $field['semantic_type'] ?? null,
                'behavior' => is_array($field['behavior'] ?? null) ? $field['behavior'] : null,
                'formula' => $field['formula'] ?? null,
                'validation' => is_array($field['validation'] ?? null) ? $field['validation'] : null,
                'options' => is_array($field['options'] ?? null) ? $field['options'] : null,
                'step_order' => (int) ($field['step_order'] ?? $index),
            ];
        }

        return $normalized;
    }

    /**
     * Legacy exports use workflow steps like:
     * {"type":"field","approverId":"approver_1","condition":{}}
     *
     * @param  list<mixed>  $steps
     * @return list<array<string, mixed>>
     */
    private function normalizeImportSteps(array $steps): array
    {
        $normalized = [];
        foreach ($steps as $index => $step) {
            if (! is_array($step)) {
                continue;
            }

            $approverId = $step['approverId'] ?? $step['approver_id'] ?? null;
            $type = (string) ($step['type'] ?? $step['approver_type'] ?? 'user');
            if ($type === 'field' || ($approverId !== null && $type === '')) {
                $type = 'field';
            }

            $condition = $step['condition'] ?? null;
            if (is_array($condition) && $condition === []) {
                $condition = null;
            }

            $normalized[] = [
                'type' => $type,
                'step_order' => (int) ($step['step_order'] ?? $index + 1),
                'approverId' => $approverId !== null ? (string) $approverId : null,
                'condition' => is_array($condition) ? $condition : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $warnings
     */
    private function normalizeLegacyBrandLogoUrl(mixed $url, array &$warnings): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '/uploads/') || str_starts_with($trimmed, 'uploads/')) {
            $warnings[] = 'Legacy brand logo path was omitted; re-upload the logo in form settings after import.';

            return null;
        }

        return $trimmed;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
