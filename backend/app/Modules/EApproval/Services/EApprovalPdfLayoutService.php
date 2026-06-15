<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

final class EApprovalPdfLayoutService
{
    private const GLOBAL_KEY = 'pdf_layout_global_default_template';

    public function __construct(
        private readonly EApprovalSettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(string $formId): array
    {
        $globalTemplate = $this->settings->getJson(self::GLOBAL_KEY, $this->defaultTemplate());

        if ($formId === 'global-default') {
            $globalStored = $this->settings->getJson(self::GLOBAL_KEY);
            $payload = $this->emptyPayload($globalTemplate);
            $payload['layout_persisted'] = $globalStored !== null;

            return $payload;
        }

        $key = $this->formKey($formId);
        $stored = $this->settings->getJson($key);

        if ($stored === null) {
            $payload = $this->buildDefaultForForm($formId, $globalTemplate);
            $payload['layout_persisted'] = false;

            return $payload;
        }

        $merged = array_merge($this->emptyPayload($globalTemplate), $stored);
        $merged['layout'] = $this->mergeLayoutWithFormFields($formId, is_array($merged['layout'] ?? null) ? $merged['layout'] : []);
        $merged['layout_persisted'] = true;

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function save(string $formId, array $payload, TenantUser $actor): void
    {
        if ($formId === 'global-default') {
            $template = $payload['template'] ?? $this->defaultTemplate();
            $this->settings->setJson(self::GLOBAL_KEY, is_array($template) ? $template : $this->defaultTemplate());

            return;
        }

        $form = EApprovalForm::query()->with('fields')->findOrFail($formId);

        $sanitized = $this->sanitizeLayoutInput($payload['layout'] ?? [], $form);
        if ($sanitized === [] || ! $this->hasVisibleLayoutRow($sanitized)) {
            throw ValidationException::withMessages([
                'layout' => [__('At least one field must remain visible.')],
            ]);
        }

        $existing = $this->settings->getJson($this->formKey($formId)) ?? [];
        $template = $payload['template'] ?? ($existing['template'] ?? $this->defaultTemplate());

        $stored = [
            'layout' => $sanitized,
            'template' => is_array($template) ? $template : $this->defaultTemplate(),
            'active_preset_id' => (string) ($payload['active_preset_id'] ?? $existing['active_preset_id'] ?? 'default'),
            'presets' => $payload['presets'] ?? ($existing['presets'] ?? []),
            'updated_at' => now()->toIso8601String(),
            'updated_by' => (string) $actor->id,
            'updated_by_name' => $actor->name,
        ];

        $this->settings->setJson($this->formKey($formId), $stored);
    }

    public function destroy(string $formId): void
    {
        if ($formId === 'global-default') {
            $this->settings->delete(self::GLOBAL_KEY);

            return;
        }

        $this->settings->delete($this->formKey($formId));
    }

    /**
     * @return list<array{key: string, label: string, visible: bool, fieldType: string}>
     */
    public function layoutRowsForForm(EApprovalForm $form): array
    {
        $form->loadMissing('fields');
        $stored = $this->settings->getJson($this->formKey((string) $form->id));

        if ($stored === null || ! is_array($stored['layout'] ?? null)) {
            return $form->fields->map(static fn ($f) => [
                'key' => $f->name,
                'label' => $f->label,
                'visible' => true,
                'fieldType' => $f->type,
            ])->values()->all();
        }

        return $this->mergeLayoutWithFormFields((string) $form->id, $stored['layout']);
    }

    /**
     * @param  list<array{key: string, label: string, visible: bool, fieldType: string}>  $sanitized
     */
    private function hasVisibleLayoutRow(array $sanitized): bool
    {
        foreach ($sanitized as $row) {
            if (! empty($row['visible'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<mixed>  $layoutInput
     * @return list<array{key: string, label: string, visible: bool, fieldType: string}>
     */
    private function sanitizeLayoutInput(array $layoutInput, EApprovalForm $form): array
    {
        $form->loadMissing('fields');
        $fieldsByName = $form->fields->keyBy('name');
        $sanitized = [];

        foreach ($layoutInput as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! $fieldsByName->has($key)) {
                continue;
            }
            $field = $fieldsByName->get($key);
            $sanitized[] = [
                'key' => $key,
                'label' => trim((string) ($row['label'] ?? $field->label)),
                'visible' => (bool) ($row['visible'] ?? false),
                'fieldType' => (string) ($row['fieldType'] ?? $field->type),
            ];
        }

        return $sanitized;
    }

    /**
     * @param  list<mixed>  $savedLayout
     * @return list<array{key: string, label: string, visible: bool, fieldType: string}>
     */
    private function mergeLayoutWithFormFields(string $formId, array $savedLayout): array
    {
        $form = EApprovalForm::query()->with('fields')->find($formId);
        if ($form === null) {
            return [];
        }

        $savedByKey = [];
        foreach ($savedLayout as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key !== '') {
                $savedByKey[$key] = $row;
            }
        }

        $merged = [];
        foreach ($form->fields as $field) {
            $saved = $savedByKey[$field->name] ?? null;
            $merged[] = [
                'key' => $field->name,
                'label' => (string) ($saved['label'] ?? $field->label),
                'visible' => $saved !== null ? (bool) ($saved['visible'] ?? false) : true,
                'fieldType' => (string) ($saved['fieldType'] ?? $field->type),
            ];
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefaultForForm(string $formId, array $globalTemplate): array
    {
        $payload = $this->emptyPayload($globalTemplate);
        $form = EApprovalForm::query()->with('fields')->find($formId);
        if ($form !== null) {
            $payload['layout'] = $form->fields->map(static fn ($f) => [
                'key' => $f->name,
                'label' => $f->label,
                'visible' => true,
                'fieldType' => $f->type,
            ])->values()->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(array $globalTemplate): array
    {
        return [
            'layout' => [],
            'template' => $globalTemplate,
            'active_preset_id' => 'default',
            'presets' => [
                ['id' => 'default', 'name' => 'Default', 'template' => $globalTemplate, 'version' => 1],
            ],
            'template_save_history' => [],
            'updated_at' => null,
            'updated_by' => null,
            'updated_by_name' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultTemplate(): array
    {
        return [
            'page' => [
                'size' => 'A4',
                'marginMm' => 12,
            ],
            'header' => [
                'showLogo' => false,
                'title' => 'E-Approval',
                'subtitle' => '',
            ],
            'footer' => [
                'showPageNumbers' => true,
                'text' => 'Generated from TowerOS E-Approval',
            ],
        ];
    }

    private function formKey(string $formId): string
    {
        return 'pdf_layout_form_'.$formId;
    }
}
