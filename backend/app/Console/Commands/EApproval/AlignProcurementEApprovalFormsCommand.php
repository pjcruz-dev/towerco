<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Models\Tenant;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalFormPolicySupport;
use Illuminate\Console\Command;

final class AlignProcurementEApprovalFormsCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    private const PR_OBSOLETE_FIELDS = [
        'project_id',
        'rollout_id',
        'site_id',
        'boq_line_id',
        'procurement_approver',
        'finance_approver',
    ];

    private const PO_OBSOLETE_FIELDS = [
        'procurement_approver',
        'finance_approver',
    ];

    protected $signature = 'e-approval:align-procurement-forms
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain hostname}
        {--all : Run for every tenant}
        {--dry-run : Preview changes without writing}
        {--form= : Optional E-Approval form UUID}
    ';

    protected $description = 'Remove obsolete PR/PO form fields (header links, per-submit approvers) and prefer Workflow tab steps.';

    public function handle(): int
    {
        $tenants = $this->resolveTenants();
        if ($tenants === []) {
            $this->error('No tenants matched. Pass --tenant=UUID, --domain=hostname, or --all.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $formId = $this->stringOption('form');

        if ($dryRun) {
            $this->warn('Dry run — no database writes will be performed.');
        }

        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->value('domain') ?? $tenant->id;

            $result = $tenant->run(function () use ($dryRun, $formId): array {
                return $this->alignTenantForms($dryRun, $formId);
            });

            $this->line(sprintf(
                '[%s] forms scanned: %d | fields removed: %d | workflow steps removed: %d | metadata updated: %d%s',
                $domain,
                $result['forms_scanned'],
                $result['fields_removed'],
                $result['steps_removed'],
                $result['metadata_updated'],
                $dryRun ? ' [dry-run]' : '',
            ));
        }

        $this->info($dryRun
            ? 'Dry run complete. Re-run without --dry-run to apply changes.'
            : 'Procurement form alignment complete. Publish forms from E-Approval if they were draft.');

        return self::SUCCESS;
    }

    /**
     * @return array{forms_scanned: int, fields_removed: int, steps_removed: int, metadata_updated: int}
     */
    private function alignTenantForms(bool $dryRun, ?string $formId): array
    {
        $result = [
            'forms_scanned' => 0,
            'fields_removed' => 0,
            'steps_removed' => 0,
            'metadata_updated' => 0,
        ];

        $query = EApprovalForm::query()
            ->with(['fields', 'workflowTemplate.steps'])
            ->when($formId, static fn ($q) => $q->where('id', $formId));

        $forms = $query->get()->filter(static function (EApprovalForm $form): bool {
            $family = EApprovalFormPolicySupport::documentFamily($form);

            return in_array($family, ['purchase_requisition', 'purchase_order'], true);
        });

        $run = function () use ($forms, $dryRun, &$result): void {
            foreach ($forms as $form) {
                $result['forms_scanned']++;
                $family = (string) EApprovalFormPolicySupport::documentFamily($form);
                $obsolete = $family === 'purchase_order' ? self::PO_OBSOLETE_FIELDS : self::PR_OBSOLETE_FIELDS;

                foreach ($form->fields as $field) {
                    if (! $field instanceof EApprovalFormField) {
                        continue;
                    }

                    if (! in_array((string) $field->name, $obsolete, true)) {
                        if (
                            ! $dryRun
                            && $family === 'purchase_order'
                            && $field->name === 'payment_terms'
                            && $field->label !== 'Payment terms'
                        ) {
                            $field->label = 'Payment terms';
                            $validation = is_array($field->validation) ? $field->validation : [];
                            $validation['help_text'] = 'Free text — describe commercial terms for this PO.';
                            $field->validation = $validation;
                            $field->save();
                        }

                        continue;
                    }

                    if (! $dryRun) {
                        $field->delete();
                    }
                    $result['fields_removed']++;
                }

                $templateId = $form->workflowTemplate?->id;
                if ($templateId) {
                    $obsoleteApproverIds = array_values(array_intersect(
                        ['procurement_approver', 'finance_approver'],
                        $obsolete,
                    ));

                    $steps = EApprovalWorkflowStep::query()
                        ->where('template_id', $templateId)
                        ->whereNull('compiled_for_submission_id')
                        ->where('approver_type', 'field')
                        ->whereIn('approver_id', $obsoleteApproverIds)
                        ->get();

                    foreach ($steps as $step) {
                        if (! $dryRun) {
                            $step->delete();
                        }
                        $result['steps_removed']++;
                    }
                }

                $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
                if (($metadata['workflow_source'] ?? null) !== 'form') {
                    if (! $dryRun) {
                        $metadata['workflow_source'] = 'form';
                        $form->metadata_json = $metadata;
                        $form->save();
                    }
                    $result['metadata_updated']++;
                }
            }
        };

        $run();

        return $result;
    }

    /** @return list<Tenant> */
    private function resolveTenants(): array
    {
        if ($this->option('all')) {
            return Tenant::query()->orderBy('id')->get()->all();
        }

        $tenant = $this->resolveTenantFromOptions();

        return $tenant instanceof Tenant ? [$tenant] : [];
    }

    private function stringOption(string $key): ?string
    {
        $value = trim((string) $this->option($key));

        return $value !== '' ? $value : null;
    }
}
