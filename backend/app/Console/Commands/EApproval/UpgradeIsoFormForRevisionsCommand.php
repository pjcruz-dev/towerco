<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Models\Tenant;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Models\EApprovalWorkflowTemplate;
use App\Modules\EApproval\Services\EApprovalFormService;
use App\Modules\EApproval\Support\EApprovalFormWorkspaceSupport;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Console\Command;

final class UpgradeIsoFormForRevisionsCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'e-approval:upgrade-iso-form-revisions
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain hostname}
        {--form= : E-Approval form UUID (required)}
        {--dry-run : Preview without saving}
    ';

    protected $description = 'Add controlled-document revision fields and sync metadata on an ISO approval form.';

    public function handle(EApprovalFormService $forms): int
    {
        $formId = trim((string) $this->option('form'));
        if ($formId === '') {
            $this->error('Pass --form=<e-approval-form-uuid>');

            return self::FAILURE;
        }

        $tenant = $this->resolveTenantFromOptions();
        if (! $tenant instanceof Tenant) {
            $this->error('Tenant not found. Use --tenant=UUID or --domain=app.towerone.localhost');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        return $tenant->run(function () use ($forms, $formId, $dryRun): int {
            $form = EApprovalForm::query()->with(['fields', 'workflowTemplate.steps'])->find($formId);
            if (! $form instanceof EApprovalForm) {
                $this->error("Form {$formId} not found.");

                return self::FAILURE;
            }

            $actor = TenantUser::query()->orderBy('created_at')->first();
            if (! $actor instanceof TenantUser) {
                $this->error('No tenant user found to attribute the form save.');

                return self::FAILURE;
            }

            $payload = $this->buildPayload($form);

            $this->info("Form: {$form->name} ({$form->id})");
            $this->line('Fields after upgrade: '.count($payload['fields']));
            $this->line('New field names: document_code, change_summary, attachments, review_date, section_revision, section_approval');
            $this->line('controlledDocumentSync: document_code field, autoRevision=true');

            if ($dryRun) {
                $this->warn('Dry run — no changes written.');

                return self::SUCCESS;
            }

            $result = $forms->update($form, $payload, $actor, true);
            $updated = $result['form'];

            foreach ($result['warnings'] as $warning) {
                $this->warn($warning);
            }

            $this->info("Saved. Status: {$updated->status}, schema_version: {$updated->schema_version}");
            $this->line('Open the form in E-Approval → Setup to verify mappings, then test a revision submission.');

            return self::SUCCESS;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(EApprovalForm $form): array
    {
        $byName = $form->fields->keyBy(static fn (EApprovalFormField $field) => (string) $field->name);

        $field = static function (string $name) use ($byName): ?EApprovalFormField {
            $match = $byName->get($name);

            return $match instanceof EApprovalFormField ? $match : null;
        };

        $id = static fn (?EApprovalFormField $existing): ?string => $existing?->id;

        $fields = [
            $this->fieldPayload($id($field('iso')), 'section', 'iso', 'ISO', 1, null, null),
            $this->fieldPayload($id($field('title')), 'text', 'title', 'Title', 2, ['required' => true], ['layout' => ['width' => 'full']]),
            $this->fieldPayload(
                $id($field('document_type')),
                'select',
                'document_type',
                'Document type',
                3,
                ['required' => true],
                [
                    'layout' => ['width' => 'half', 'row_id' => 'row_mqt3qnjf', 'slot' => 0, 'row_columns' => 2],
                    'master_data_key' => 'document-type',
                ],
            ),
            $this->fieldPayload(
                $id($field('department')),
                'select',
                'department',
                'Department',
                4,
                ['required' => true],
                [
                    'layout' => ['width' => 'half', 'row_id' => 'row_mqt3qnjf', 'slot' => 1, 'row_columns' => 2],
                    'master_data_key' => 'cost-center',
                ],
            ),
            $this->fieldPayload(
                $id($field('effective_date')),
                'date',
                'effective_date',
                'Effective date',
                5,
                ['required' => true],
                ['layout' => ['width' => 'full']],
            ),
            $this->fieldPayload($id($field('section_revision')), 'section', 'section_revision', 'Document identification', 6, null, null),
            $this->fieldPayload(
                $id($field('previous_revision')),
                'number',
                'previous_revision',
                'Previous revision',
                8,
                [
                    'required' => false,
                    'help_text' => 'Prefilled from the registry on revision requests.',
                ],
                ['layout' => ['width' => 'half', 'row_id' => 'iso_rev_code', 'slot' => 0, 'row_columns' => 2]],
            ),
            $this->fieldPayload(
                $id($field('revision')),
                'number',
                'revision',
                'Current revision (auto)',
                9,
                [
                    'required' => false,
                    'min' => 0,
                    'help_text' => 'Assigned automatically. Leave blank unless you need to override.',
                ],
                ['layout' => ['width' => 'half', 'row_id' => 'iso_rev_code', 'slot' => 1, 'row_columns' => 2]],
            ),
            $this->fieldPayload(
                $id($field('document_code')),
                'text',
                'document_code',
                'Document number',
                10,
                [
                    'required' => false,
                    'help_text' => 'Leave blank for a new document. Selected from registry for revisions.',
                ],
                ['layout' => ['width' => 'full']],
            ),
            $this->fieldPayload($id($field('section_change')), 'section', 'section_change', 'Details of change', 11, null, null),
            $this->fieldPayload(
                $id($field('change_summary')),
                'textarea',
                'change_summary',
                'Details of change',
                12,
                [
                    'required' => false,
                    'help_text' => 'Describe what was modified in the document.',
                ],
                ['layout' => ['width' => 'full']],
            ),
            $this->fieldPayload(
                $id($field('reason_for_change')),
                'textarea',
                'reason_for_change',
                'Reason for change',
                13,
                [
                    'required' => false,
                    'help_text' => 'Why this revision is needed (audit, regulation, process improvement, etc.).',
                ],
                ['layout' => ['width' => 'full']],
            ),
            $this->fieldPayload($id($field('section_approval')), 'section', 'section_approval', 'Authorization', 14, null, null),
            $this->fieldPayload(
                $id($field('head_approval')),
                'approver',
                'head_approval',
                'Head approval',
                15,
                ['required' => true],
                ['layout' => ['width' => 'full']],
            ),
            $this->fieldPayload(
                $id($field('attachments')),
                'file',
                'attachments',
                'Controlled document file',
                16,
                ['required' => false, 'maxFileSizeMb' => 25, 'allowedFileTypes' => ['pdf', 'doc', 'docx', 'xlsx', 'pptx']],
                ['layout' => ['width' => 'full']],
            ),
        ];

        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $metadata['form_family'] = 'iso_document_control';
        $metadata['workspace'] = EApprovalFormWorkspaceSupport::isoPilotDefaults((string) $form->name);
        $metadata['builder_layout_rows'] = [
            ['id' => 'row_mqt3qnjf', 'columns' => 2, 'insert_index' => 6],
            ['id' => 'row_mqt3zdp1', 'columns' => 2, 'insert_index' => 8],
            ['id' => 'iso_rev_code', 'columns' => 2, 'insert_index' => 10],
        ];
        $metadata['controlledDocumentSync'] = [
            'enabled' => true,
            'autoRevision' => true,
            'documentCodeField' => 'document_code',
            'attachmentField' => 'attachments',
            'composeUi' => [
                'hideSectionProgress' => true,
                'hideRegistryPicker' => true,
            ],
            'fieldMap' => [
                'title' => 'title',
                'document_type' => 'document_type',
                'department' => 'department',
                'revision_number' => 'revision',
                'effective_date' => 'effective_date',
                'change_summary' => 'change_summary',
            ],
        ];
        $metadata['default_controlled_document_form'] = true;
        $metadata['effective_workflow_source'] = $metadata['effective_workflow_source'] ?? 'form';

        return [
            'name' => $form->name,
            'description' => $form->description ?? 'ISO document control with DCF fields.',
            'status' => $form->status,
            'category' => $form->category,
            'metadata_json' => $metadata,
            'accepts_new_submissions' => $form->accepts_new_submissions !== false,
            'owner_code' => $form->owner_code ?? 'GEN',
            'doc_type_code' => $form->doc_type_code ?? 'F',
            'doc_no_custom_enabled' => (bool) $form->doc_no_custom_enabled,
            'doc_no_template' => $form->doc_no_template ?? 'ATC-{department}-{document_type}-{seq:3}',
            'fields' => $fields,
            'steps' => $this->buildStepsPayload($form),
            'confirm_form_upgrade' => true,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildStepsPayload(EApprovalForm $form): array
    {
        $template = $form->workflowTemplate;
        if (! $template instanceof EApprovalWorkflowTemplate) {
            return [];
        }

        $steps = EApprovalWorkflowStep::query()
            ->where('template_id', $template->id)
            ->whereNull('compiled_for_submission_id')
            ->orderBy('step_order')
            ->get()
            ->unique(static fn (EApprovalWorkflowStep $step) => $step->step_order.'|'.($step->approver_id ?? ''))
            ->values();

        $payload = [];
        foreach ($steps as $index => $step) {
            $type = (string) $step->approver_type;
            $mappedType = $type === 'field' ? 'field' : 'user';
            $payload[] = [
                'id' => $step->id,
                'type' => $mappedType,
                'approverId' => (string) ($step->approver_id ?? ''),
                'step_order' => $index + 1,
                'condition' => is_array($step->condition) ? $step->condition : null,
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $validation
     * @param  array<string, mixed>|null  $options
     * @return array<string, mixed>
     */
    private function fieldPayload(
        ?string $id,
        string $type,
        string $name,
        string $label,
        int $stepOrder,
        ?array $validation,
        ?array $options,
    ): array {
        $row = [
            'type' => $type,
            'name' => $name,
            'label' => $label,
            'semantic_type' => null,
            'behavior' => [],
            'formula' => null,
            'validation' => $validation,
            'options' => $options,
            'step_order' => $stepOrder,
        ];

        if (is_string($id) && $id !== '') {
            $row['id'] = $id;
        }

        return $row;
    }
}
