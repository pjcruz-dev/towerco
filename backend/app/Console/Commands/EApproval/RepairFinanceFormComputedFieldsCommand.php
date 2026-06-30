<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Modules\EApproval\Models\EApprovalForm;
use Illuminate\Console\Command;

class RepairFinanceFormComputedFieldsCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'e-approval:repair-finance-computed-fields
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain}
        {--form= : Optional form UUID}
    ';

    protected $description = 'Repair finance form computed totals (options + field order) from bundle templates.';

    public function handle(): int
    {
        $tenant = $this->resolveTenantFromOptions();
        if ($tenant === null) {
            $this->error('Tenant not found. Pass --tenant=UUID or --domain=hostname.');

            return self::FAILURE;
        }

        $templates = config('e_approval_finance_procurement_templates', []);
        $formId = $this->option('form');

        $repaired = $tenant->run(function () use ($templates, $formId): int {
            $count = 0;
            $query = EApprovalForm::query()->with('fields');

            if (is_string($formId) && $formId !== '') {
                $query->where('id', $formId);
            }

            $query->each(function (EApprovalForm $form) use ($templates, &$count): void {
                $family = is_array($form->metadata_json) ? (string) ($form->metadata_json['form_family'] ?? '') : '';
                if ($family === '' || ! isset($templates[$family])) {
                    return;
                }

                $template = $templates[$family];
                $templateFields = collect($template['fields'] ?? [])->keyBy('name');
                $changed = false;

                foreach ($form->fields as $field) {
                    $templateField = $templateFields->get((string) $field->name);
                    if (! is_array($templateField)) {
                        continue;
                    }

                    $updates = [];
                    if (isset($templateField['step_order'])) {
                        $updates['step_order'] = (int) $templateField['step_order'];
                    }
                    if (array_key_exists('options', $templateField)) {
                        $updates['options'] = $templateField['options'];
                    }
                    if (array_key_exists('validation', $templateField)) {
                        $updates['validation'] = $templateField['validation'];
                    }

                    if ($updates === []) {
                        continue;
                    }

                    $field->fill($updates);
                    if ($field->isDirty()) {
                        $field->save();
                        $changed = true;
                    }
                }

                if ($changed) {
                    $count++;
                    $this->line("Repaired form [{$form->id}] {$form->name}");
                }
            });

            return $count;
        });

        $this->info("Repaired {$repaired} finance form(s) on tenant [{$tenant->id}].");

        return self::SUCCESS;
    }
}
