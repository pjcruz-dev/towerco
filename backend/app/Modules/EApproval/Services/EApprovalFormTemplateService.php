<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalFormTemplateService
{
    public const TENANT_TEMPLATES_KEY = 'tenant_form_templates';

    public function __construct(
        private readonly EApprovalSettingsService $settings,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return array_merge($this->systemTemplates(), $this->tenantTemplates());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function financeProcurementBundleDefinition(): ?array
    {
        $financeTemplates = config('e_approval_finance_procurement_templates', []);
        if (! is_array($financeTemplates)) {
            return null;
        }

        $bundle = $financeTemplates['_bundle'] ?? null;

        return is_array($bundle) ? $bundle : null;
    }

    /**
     * @return array{forms: list<array<string, mixed>>, warnings: list<string>}
     */
    public function createFinanceProcurementBundle(TenantUser $actor, EApprovalFormService $forms): array
    {
        $bundle = $this->financeProcurementBundleDefinition();
        if ($bundle === null) {
            throw ValidationException::withMessages([
                'bundle' => [__('Finance & procurement bundle is not configured.')],
            ]);
        }

        $templateIds = $bundle['template_ids'] ?? [];
        if (! is_array($templateIds) || $templateIds === []) {
            throw ValidationException::withMessages([
                'bundle' => [__('Finance & procurement bundle has no templates.')],
            ]);
        }

        $warnings = [];
        /** @var array<string, EApprovalForm> $createdByTemplate */
        $createdByTemplate = [];

        foreach ($templateIds as $templateId) {
            if (! is_string($templateId) || $templateId === '') {
                continue;
            }

            $existing = $this->findFormForTemplate($templateId);
            if ($existing instanceof EApprovalForm) {
                $createdByTemplate[$templateId] = $existing;
                $warnings[] = __('Form for template :template already exists; reusing.', ['template' => $templateId]);

                continue;
            }

            $result = $this->createFromTemplate($templateId, $actor, $forms, skipRelatedResolution: true);
            $createdByTemplate[$templateId] = $result['form'];
            $warnings = array_merge($warnings, $result['warnings']);
        }

        $this->wireRelatedFormIds($createdByTemplate);

        return [
            'forms' => collect($createdByTemplate)
                ->map(static fn (EApprovalForm $form): array => $form->fresh(['fields', 'workflowTemplate.steps'])->toDetailPayload())
                ->values()
                ->all(),
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{form: EApprovalForm, warnings: list<string>}
     */
    public function createFromTemplate(
        string $templateId,
        TenantUser $actor,
        EApprovalFormService $forms,
        bool $skipRelatedResolution = false,
    ): array {
        $definition = $this->resolveDefinition($templateId);
        if ($definition === null) {
            throw ValidationException::withMessages([
                'template_id' => [__('Form template not found.')],
            ]);
        }

        $metadata = is_array($definition['metadata_json'] ?? null) ? $definition['metadata_json'] : [];
        $metadata['created_from_template'] = $templateId;

        $payload = [
            'name' => (string) ($definition['name'] ?? 'New form'),
            'description' => $definition['description'] ?? null,
            'category' => $definition['category'] ?? 'general',
            'status' => 'draft',
            'fields' => $definition['fields'] ?? [],
            'steps' => $definition['steps'] ?? [],
            'metadata_json' => $metadata,
        ];

        if (isset($definition['doc_type_code']) && is_string($definition['doc_type_code'])) {
            $payload['doc_type_code'] = $definition['doc_type_code'];
        }

        if (isset($definition['owner_code']) && is_string($definition['owner_code'])) {
            $payload['owner_code'] = $definition['owner_code'];
        }

        if (isset($definition['related_form_ids']) && is_array($definition['related_form_ids'])) {
            $payload['related_form_ids'] = $definition['related_form_ids'];
        } elseif (! $skipRelatedResolution) {
            $relatedTemplateIds = $metadata['related_template_ids'] ?? [];
            if (is_array($relatedTemplateIds) && $relatedTemplateIds !== []) {
                $resolved = $this->resolveRelatedFormIds($relatedTemplateIds);
                if ($resolved !== []) {
                    $payload['related_form_ids'] = $resolved;
                }
            }
        }

        return $forms->create($payload, $actor);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upsertTenantTemplate(?string $templateId, array $payload): array
    {
        $templates = $this->loadTenantTemplates();
        $id = $templateId !== null && $templateId !== ''
            ? $templateId
            : 'custom_'.Str::lower(Str::random(10));

        if ($templateId !== null && $templateId !== '' && ! isset($templates[$id])) {
            throw ValidationException::withMessages([
                'template_id' => [__('Tenant template not found.')],
            ]);
        }

        if (str_starts_with($id, 'leave_') || str_starts_with($id, 'purchase_') || str_starts_with($id, 'employee_')) {
            throw ValidationException::withMessages([
                'id' => [__('This template id is reserved for system templates.')],
            ]);
        }

        $fields = $payload['fields'] ?? [];
        $steps = $payload['steps'] ?? [];
        if (! is_array($fields) || count($fields) === 0) {
            throw ValidationException::withMessages([
                'fields' => [__('At least one field is required.')],
            ]);
        }

        $templates[$id] = [
            'name' => trim((string) ($payload['name'] ?? 'Custom template')),
            'description' => $payload['description'] ?? null,
            'category' => $payload['category'] ?? 'general',
            'fields' => $fields,
            'steps' => is_array($steps) ? $steps : [],
            'updated_at' => now()->toIso8601String(),
        ];

        $this->saveTenantTemplates($templates);

        return $this->formatTenantRow($id, $templates[$id]);
    }

    public function deleteTenantTemplate(string $templateId): void
    {
        $templates = $this->loadTenantTemplates();
        if (! isset($templates[$templateId])) {
            throw ValidationException::withMessages([
                'template_id' => [__('Tenant template not found.')],
            ]);
        }

        unset($templates[$templateId]);
        $this->saveTenantTemplates($templates);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDefinition(string $templateId): ?array
    {
        $system = config('e_approval.form_templates', []);
        if (is_array($system) && isset($system[$templateId]) && is_array($system[$templateId])) {
            return $system[$templateId];
        }

        $tenant = $this->loadTenantTemplates();

        return isset($tenant[$templateId]) && is_array($tenant[$templateId])
            ? $tenant[$templateId]
            : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function systemTemplates(): array
    {
        $templates = config('e_approval.form_templates', []);

        return collect(is_array($templates) ? $templates : [])
            ->reject(static fn (mixed $t, string $id): bool => str_starts_with($id, '_') || ! is_array($t))
            ->map(static fn (array $t, string $id): array => [
                'id' => $id,
                'name' => (string) ($t['name'] ?? $id),
                'description' => (string) ($t['description'] ?? ''),
                'category' => (string) ($t['category'] ?? 'general'),
                'field_count' => is_array($t['fields'] ?? null) ? count($t['fields']) : 0,
                'step_count' => is_array($t['steps'] ?? null) ? count($t['steps']) : 0,
                'source' => 'system',
                'editable' => false,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantTemplates(): array
    {
        return collect($this->loadTenantTemplates())
            ->map(fn (array $t, string $id): array => $this->formatTenantRow($id, $t))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function formatTenantRow(string $id, array $template): array
    {
        return [
            'id' => $id,
            'name' => (string) ($template['name'] ?? $id),
            'description' => (string) ($template['description'] ?? ''),
            'category' => (string) ($template['category'] ?? 'general'),
            'field_count' => is_array($template['fields'] ?? null) ? count($template['fields']) : 0,
            'step_count' => is_array($template['steps'] ?? null) ? count($template['steps']) : 0,
            'source' => 'tenant',
            'editable' => true,
            'updated_at' => $template['updated_at'] ?? null,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadTenantTemplates(): array
    {
        $raw = $this->settings->getString(self::TENANT_TEMPLATES_KEY);
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $templates
     */
    private function saveTenantTemplates(array $templates): void
    {
        $this->settings->setString(
            self::TENANT_TEMPLATES_KEY,
            json_encode($templates, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function tenantTemplateDefinition(string $templateId): ?array
    {
        $templates = $this->loadTenantTemplates();

        return isset($templates[$templateId]) && is_array($templates[$templateId])
            ? $templates[$templateId]
            : null;
    }

    /**
     * @param  list<string>  $relatedTemplateIds
     * @return list<string>
     */
    private function resolveRelatedFormIds(array $relatedTemplateIds): array
    {
        $resolved = [];

        foreach ($relatedTemplateIds as $relatedTemplateId) {
            if (! is_string($relatedTemplateId) || $relatedTemplateId === '') {
                continue;
            }

            $form = $this->findFormForTemplate($relatedTemplateId);
            if ($form instanceof EApprovalForm) {
                $resolved[] = (string) $form->id;
            }
        }

        return array_values(array_unique($resolved));
    }

    private function findFormForTemplate(string $templateId): ?EApprovalForm
    {
        $forms = EApprovalForm::query()->get();

        foreach ($forms as $form) {
            $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];

            if (($metadata['created_from_template'] ?? null) === $templateId) {
                return $form;
            }

            if (($metadata['form_family'] ?? null) === $templateId) {
                return $form;
            }
        }

        return null;
    }

    /**
     * @param  array<string, EApprovalForm>  $formsByTemplate
     */
    private function wireRelatedFormIds(array $formsByTemplate): void
    {
        foreach ($formsByTemplate as $templateId => $form) {
            $definition = $this->resolveDefinition($templateId);
            if ($definition === null) {
                continue;
            }

            $metadata = is_array($definition['metadata_json'] ?? null) ? $definition['metadata_json'] : [];
            $relatedTemplateIds = $metadata['related_template_ids'] ?? [];
            if (! is_array($relatedTemplateIds) || $relatedTemplateIds === []) {
                continue;
            }

            $relatedFormIds = [];
            foreach ($relatedTemplateIds as $relatedTemplateId) {
                if (! is_string($relatedTemplateId) || $relatedTemplateId === '') {
                    continue;
                }

                if (isset($formsByTemplate[$relatedTemplateId])) {
                    $relatedFormIds[] = (string) $formsByTemplate[$relatedTemplateId]->id;
                }
            }

            if ($relatedFormIds === []) {
                continue;
            }

            $form->related_form_ids = array_values(array_unique($relatedFormIds));
            $form->save();
        }
    }
}
