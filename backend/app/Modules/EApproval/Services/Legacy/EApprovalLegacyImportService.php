<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services\Legacy;

use App\Modules\EApproval\Models\EApprovalDelegation;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Models\EApprovalWorkflowTemplate;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use App\Modules\Identity\Models\TenantUser;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalLegacyImportService
{
    /** @var array<string, string> */
    private array $userMap = [];

    public function __construct(
        private readonly EApprovalSettingsService $settings,
    ) {}

    /**
     * @param  list<string>  $only  Empty = all sections; else subset: users,forms,submissions,master-data,settings,delegations
     */
    public function run(string $connection, bool $dryRun = false, array $only = []): EApprovalLegacyImportResult
    {
        $legacy = $this->connection($connection);
        $result = new EApprovalLegacyImportResult(dryRun: $dryRun);
        $sections = $only === [] ? ['users', 'forms', 'submissions', 'master-data', 'settings', 'delegations'] : $only;

        if (in_array('users', $sections, true)) {
            $this->buildUserMap($legacy, $result);
        }

        $run = function () use ($legacy, $dryRun, $sections, $result): void {
            if (in_array('forms', $sections, true)) {
                $this->importForms($legacy, $dryRun, $result);
            }
            if (in_array('submissions', $sections, true)) {
                $this->importSubmissions($legacy, $dryRun, $result);
            }
            if (in_array('master-data', $sections, true)) {
                $this->importMasterData($legacy, $dryRun, $result);
            }
            if (in_array('settings', $sections, true)) {
                $this->importSettings($legacy, $dryRun, $result);
            }
            if (in_array('delegations', $sections, true)) {
                $this->importDelegations($legacy, $dryRun, $result);
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        return $result;
    }

    private function connection(string $name): Connection
    {
        if (! config("database.connections.{$name}")) {
            throw ValidationException::withMessages([
                'connection' => [__('Database connection [:name] is not configured.', ['name' => $name])],
            ]);
        }

        try {
            return DB::connection($name);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'connection' => [__('Cannot connect to legacy database: :message', ['message' => $e->getMessage()])],
            ]);
        }
    }

    private function buildUserMap(Connection $legacy, EApprovalLegacyImportResult $result): void
    {
        $this->userMap = [];
        $legacyUsers = $legacy->table('users')->select(['id', 'email'])->get();

        foreach ($legacyUsers as $row) {
            $email = strtolower(trim((string) ($row->email ?? '')));
            if ($email === '') {
                continue;
            }

            $tenantUser = TenantUser::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($tenantUser === null) {
                $result->warnings[] = "No TowerOS user for legacy email {$email}; related rows may be skipped.";

                continue;
            }

            $this->userMap[(string) $row->id] = (string) $tenantUser->id;
            $result->usersMapped++;
        }
    }

    private function importForms(Connection $legacy, bool $dryRun, EApprovalLegacyImportResult $result): void
    {
        $forms = $legacy->table('forms')->orderBy('created_at')->get();

        if (! $dryRun && $legacy->getSchemaBuilder()->hasTable('document_sequences')) {
            foreach ($legacy->table('document_sequences')->get() as $seq) {
                DB::table('e_approval_document_sequences')->updateOrInsert(
                    ['prefix' => (string) $seq->prefix],
                    ['next_no' => (int) $seq->next_no],
                );
            }
        }

        foreach ($forms as $form) {
            $formId = (string) $form->id;
            if ($dryRun) {
                $result->formsImported++;

                continue;
            }

            if (EApprovalForm::query()->where('id', $formId)->exists()) {
                $result->warnings[] = "Form {$formId} already exists; skipped.";

                continue;
            }

            EApprovalForm::query()->create([
                'id' => $formId,
                'name' => (string) $form->name,
                'description' => $form->description,
                'category' => (string) ($form->category ?? 'general'),
                'metadata_json' => $this->decodeJson($form->metadata_json ?? null),
                'restricted_to' => $form->restricted_to,
                'status' => (string) ($form->status ?? 'published'),
                'schema_version' => (int) ($form->schema_version ?? 1),
                'published_snapshot' => $form->published_snapshot,
                'owner_code' => (string) ($form->owner_code ?? 'GEN'),
                'doc_type_code' => (string) ($form->doc_type_code ?? 'F'),
                'doc_no_custom_enabled' => (bool) ($form->doc_no_custom_enabled ?? false),
                'doc_no_template' => $form->doc_no_template,
                'doc_no_seq_start' => $form->doc_no_seq_start,
                'doc_no_seq_start_rules' => $this->decodeJson($form->doc_no_seq_start_rules ?? null),
                'brand_logo_url' => $form->brand_logo_url,
                'brand_primary_color' => $form->brand_primary_color,
                'related_form_ids' => $form->related_form_ids,
                'created_at' => $form->created_at,
                'updated_at' => $form->updated_at ?? $form->created_at,
            ]);

            $fields = $legacy->table('form_fields')->where('form_id', $formId)->orderBy('step_order')->get();
            foreach ($fields as $field) {
                EApprovalFormField::query()->create([
                    'id' => (string) $field->id,
                    'form_id' => $formId,
                    'type' => (string) $field->type,
                    'name' => (string) $field->name,
                    'label' => (string) $field->label,
                    'semantic_type' => $field->semantic_type ?? null,
                    'behavior' => $this->decodeJson($field->behavior ?? null),
                    'formula' => $field->formula,
                    'validation' => $this->decodeJson($field->validation ?? null),
                    'options' => $this->decodeJson($field->options ?? null),
                    'step_order' => (int) ($field->step_order ?? 0),
                ]);
            }

            $template = $legacy->table('workflow_templates')->where('form_id', $formId)->first();
            if ($template !== null) {
                $templateId = (string) $template->id;
                EApprovalWorkflowTemplate::query()->create([
                    'id' => $templateId,
                    'form_id' => $formId,
                ]);

                $steps = $legacy->table('workflow_steps')->where('template_id', $templateId)->orderBy('step_order')->get();
                foreach ($steps as $step) {
                    EApprovalWorkflowStep::query()->create([
                        'id' => (string) $step->id,
                        'template_id' => $templateId,
                        'step_order' => (int) $step->step_order,
                        'approver_type' => (string) $step->approver_type,
                        'approver_id' => $step->approver_id,
                        'condition' => $this->decodeJson($step->condition ?? null),
                    ]);
                }
            }

            $result->formsImported++;
        }
    }

    private function importSubmissions(Connection $legacy, bool $dryRun, EApprovalLegacyImportResult $result): void
    {
        $submissions = $legacy->table('form_submissions')->orderBy('created_at')->get();

        foreach ($submissions as $sub) {
            $requestorId = $this->userMap[(string) $sub->user_id] ?? null;
            if ($requestorId === null) {
                $result->submissionsSkipped++;
                $result->warnings[] = "Skipped submission {$sub->id}: requestor not mapped.";

                continue;
            }

            $subId = (string) $sub->id;
            if (! $dryRun && EApprovalSubmission::query()->where('id', $subId)->exists()) {
                $result->submissionsSkipped++;

                continue;
            }

            if ($dryRun) {
                $result->submissionsImported++;

                continue;
            }

            EApprovalSubmission::query()->create([
                'id' => $subId,
                'document_no' => (string) $sub->document_no,
                'form_id' => (string) $sub->form_id,
                'requestor_id' => $requestorId,
                'status' => (string) ($sub->status ?? 'pending'),
                'current_step' => (int) ($sub->current_step ?? 1),
                'parent_submission_id' => $sub->parent_submission_id,
                'schema_snapshot_json' => $this->decodeJson($sub->schema_snapshot_json ?? null),
                'workflow_snapshot_json' => $this->decodeJson($sub->workflow_snapshot_json ?? null),
                'workflow_version_id' => $sub->workflow_version_id,
                'created_at' => $sub->created_at,
                'updated_at' => $sub->updated_at ?? $sub->created_at,
            ]);

            $values = $legacy->table('form_values')->where('submission_id', $subId)->get();
            foreach ($values as $val) {
                EApprovalFormValue::query()->create([
                    'id' => (string) $val->id,
                    'submission_id' => $subId,
                    'field_id' => (string) $val->field_id,
                    'value' => $val->value,
                ]);
            }

            $approvals = $legacy->table('request_approvals')->where('submission_id', $subId)->get();
            foreach ($approvals as $appr) {
                $approverId = $appr->approver_id ? ($this->userMap[(string) $appr->approver_id] ?? null) : null;
                EApprovalRequestApproval::query()->create([
                    'id' => (string) $appr->id,
                    'submission_id' => $subId,
                    'step_id' => (string) $appr->step_id,
                    'approver_id' => $approverId,
                    'status' => (string) ($appr->status ?? 'pending'),
                    'remarks' => $appr->remarks,
                    'acted_at' => $appr->acted_at,
                    'signature' => $appr->signature,
                    'last_reminder_at' => $appr->last_reminder_at ?? null,
                    'escalated_at' => $appr->escalated_at ?? null,
                    'created_at' => $appr->created_at ?? now(),
                    'updated_at' => $appr->updated_at ?? $appr->created_at ?? now(),
                ]);
            }

            $result->submissionsImported++;
        }
    }

    private function importMasterData(Connection $legacy, bool $dryRun, EApprovalLegacyImportResult $result): void
    {
        if (! $legacy->getSchemaBuilder()->hasTable('master_data_sets')) {
            return;
        }

        $sets = $legacy->table('master_data_sets')->get();
        foreach ($sets as $set) {
            $setId = (string) $set->id;
            if ($dryRun) {
                $result->masterDataSetsImported++;

                continue;
            }

            EApprovalMasterDataSet::query()->updateOrCreate(
                ['key' => (string) $set->key],
                [
                    'id' => $setId,
                    'name' => (string) ($set->name ?? $set->key),
                    'status' => (string) ($set->status ?? 'active'),
                    'config_json' => $this->decodeJson($set->config_json ?? null),
                ],
            );

            if (! $legacy->getSchemaBuilder()->hasTable('master_data_rows')) {
                continue;
            }

            $rows = $legacy->table('master_data_rows')->where('set_id', $setId)->get();
            foreach ($rows as $row) {
                EApprovalMasterDataRow::query()->updateOrCreate(
                    ['id' => (string) $row->id],
                    [
                        'set_id' => $setId,
                        'code' => $row->code,
                        'label' => (string) $row->label,
                        'data_json' => $this->decodeJson($row->data_json ?? null),
                        'sort_order' => (int) ($row->sort_order ?? 0),
                        'is_active' => (bool) ($row->is_active ?? true),
                    ],
                );
            }

            $result->masterDataSetsImported++;
        }
    }

    private function importSettings(Connection $legacy, bool $dryRun, EApprovalLegacyImportResult $result): void
    {
        if (! $legacy->getSchemaBuilder()->hasTable('system_settings')) {
            return;
        }

        $keys = [
            EApprovalSettingsService::SLA_REMINDER_MINUTES,
            EApprovalSettingsService::SLA_ESCALATION_MINUTES,
            EApprovalSettingsService::MANUAL_FOLLOW_UP_COOLDOWN_MINUTES,
            EApprovalSettingsService::FEATURE_DELEGATION_UI,
        ];

        $legacyKeys = [
            'sla_reminder_minutes',
            'sla_escalation_minutes',
            'manual_follow_up_cooldown_minutes',
            'feature_delegation_ui',
            'auto_follow_up_sla_minutes',
        ];

        foreach ($legacyKeys as $key) {
            $row = $legacy->table('system_settings')->where('key', $key)->first();
            if ($row === null || $row->value === null) {
                continue;
            }

            $targetKey = match ($key) {
                'auto_follow_up_sla_minutes' => EApprovalSettingsService::SLA_REMINDER_MINUTES,
                default => $key,
            };

            if (! in_array($targetKey, $keys, true)) {
                continue;
            }

            if ($dryRun) {
                $result->settingsImported++;

                continue;
            }

            $this->settings->setString($targetKey, (string) $row->value);
            $result->settingsImported++;
        }
    }

    private function importDelegations(Connection $legacy, bool $dryRun, EApprovalLegacyImportResult $result): void
    {
        $users = $legacy->table('users')
            ->whereNotNull('delegated_to')
            ->where('delegated_to', '!=', '')
            ->get();

        foreach ($users as $row) {
            $delegatorId = $this->userMap[(string) $row->id] ?? null;
            $delegateId = $this->userMap[(string) $row->delegated_to] ?? null;
            if ($delegatorId === null || $delegateId === null) {
                continue;
            }

            $validUntil = $row->delegated_until
                ? Carbon::parse((string) $row->delegated_until)->startOfDay()
                : null;

            if ($dryRun) {
                $result->delegationsImported++;

                continue;
            }

            EApprovalDelegation::query()->create([
                'id' => (string) Str::uuid(),
                'delegator_id' => $delegatorId,
                'delegate_id' => $delegateId,
                'valid_from' => Carbon::today(),
                'valid_until' => $validUntil,
                'notes' => 'Imported from legacy users.delegated_to',
                'is_active' => $validUntil === null || $validUntil->gte(Carbon::today()),
            ]);
            $result->delegationsImported++;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
