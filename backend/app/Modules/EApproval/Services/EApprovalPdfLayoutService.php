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

            return $this->withPresentedSubsidiaryLogos($formId, $payload);
        }

        $merged = array_merge($this->emptyPayload($globalTemplate), $stored);
        $merged['layout'] = $this->mergeLayoutWithFormFields($formId, is_array($merged['layout'] ?? null) ? $merged['layout'] : []);
        $merged['layout_persisted'] = true;

        return $this->withPresentedSubsidiaryLogos($formId, $merged);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withPresentedSubsidiaryLogos(string $formId, array $payload): array
    {
        if ($formId === 'global-default') {
            return $payload;
        }

        $template = is_array($payload['template'] ?? null) ? $payload['template'] : [];
        $presented = $this->presentSubsidiaryLogoUrls($formId, $template);
        $template['subsidiary_logos'] = $presented;
        $template['subsidiary_codes'] = $this->presentSubsidiaryCodes($template);
        if (! isset($template['subsidiary_logo_field']) || trim((string) $template['subsidiary_logo_field']) === '') {
            $template['subsidiary_logo_field'] = 'subsidiary';
        }
        $payload['template'] = $template;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function save(string $formId, array $payload, TenantUser $actor): void
    {
        if ($formId === 'global-default') {
            $template = $payload['template'] ?? $this->defaultTemplate();
            $this->settings->setJson(
                self::GLOBAL_KEY,
                $this->sanitizeTemplate(is_array($template) ? $template : $this->defaultTemplate()),
            );

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
        if (! is_array($template)) {
            $template = $this->defaultTemplate();
        }
        $existingTemplate = is_array($existing['template'] ?? null) ? $existing['template'] : [];
        $template = $this->mergeSubsidiaryLogoPersistence($existingTemplate, $template);
        $template = $this->sanitizeTemplate($template);
        $template['subsidiary_codes'] = $this->resolveSubsidiaryCodesForPersist($template);

        $stored = [
            'layout' => $sanitized,
            'template' => $template,
            'active_preset_id' => (string) ($payload['active_preset_id'] ?? $existing['active_preset_id'] ?? 'default'),
            'presets' => $payload['presets'] ?? ($existing['presets'] ?? []),
            'updated_at' => now()->toIso8601String(),
            'updated_by' => (string) $actor->id,
            'updated_by_name' => $actor->name,
        ];

        $this->settings->setJson($this->formKey($formId), $stored);
        $this->syncSubsidiaryFieldChoices($formId, $template);
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
            $payload['template'] = $this->applyExpenseFormPrintDefaults($form, is_array($payload['template'] ?? null) ? $payload['template'] : $globalTemplate);
        }

        return $payload;
    }

    /**
     * Wide expense grids (liquidation / reimbursement) default to landscape + tighter margins.
     *
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function applyExpenseFormPrintDefaults(EApprovalForm $form, array $template): array
    {
        if (! $this->formPrefersLandscapePrint($form)) {
            return $template;
        }

        $template['orientation'] = 'landscape';
        $page = is_array($template['page'] ?? null) ? $template['page'] : [];
        $template['page'] = [
            'size' => (string) ($page['size'] ?? 'A4'),
            'marginMm' => isset($page['marginMm']) ? (int) $page['marginMm'] : 8,
        ];

        return $template;
    }

    private function formPrefersLandscapePrint(EApprovalForm $form): bool
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $family = strtolower(trim((string) ($metadata['form_family'] ?? '')));
        if (in_array($family, ['liquidation', 'reimbursement'], true)) {
            return true;
        }

        $orientationHint = strtolower(trim((string) ($metadata['print_default_orientation'] ?? '')));
        if ($orientationHint === 'landscape') {
            return true;
        }

        $form->loadMissing('fields');

        return $form->fields->contains(
            static fn ($field): bool => (string) $field->type === 'grid'
                && str_contains(strtolower((string) $field->name), 'expense'),
        );
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
            'orientation' => 'portrait',
            'subsidiary_logo_field' => 'subsidiary',
            'subsidiary_codes' => ['ATC', 'ADIC'],
            'subsidiary_logos' => [],
            'header' => [
                'showLogo' => false,
                'title' => 'E-Forms',
                'subtitle' => '',
            ],
            'footer' => [
                'showPageNumbers' => true,
                'showApprovalHistory' => true,
                'showRequestorSignature' => false,
                'appendAttachments' => true,
                'text' => 'Generated from INFRA SUITE E-Forms',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function sanitizeTemplate(array $template): array
    {
        $maxLen = 200_000;

        if (array_key_exists('template_html', $template)) {
            $html = is_string($template['template_html']) ? $template['template_html'] : '';
            if (strlen($html) > $maxLen) {
                throw ValidationException::withMessages([
                    'template.template_html' => [__('Document design HTML exceeds the maximum length.')],
                ]);
            }
            $template['template_html'] = $html;
        }

        if (array_key_exists('template_css', $template)) {
            $css = is_string($template['template_css']) ? $template['template_css'] : '';
            if (strlen($css) > $maxLen) {
                throw ValidationException::withMessages([
                    'template.template_css' => [__('Document design CSS exceeds the maximum length.')],
                ]);
            }
            $template['template_css'] = $css;
        }

        if (array_key_exists('orientation', $template)) {
            $orientation = strtolower(trim((string) $template['orientation']));
            $template['orientation'] = in_array($orientation, ['portrait', 'landscape'], true)
                ? $orientation
                : 'portrait';
        }

        if (array_key_exists('page', $template)) {
            $page = is_array($template['page']) ? $template['page'] : [];
            $sizeRaw = strtolower(trim((string) ($page['size'] ?? 'A4')));
            $size = match ($sizeRaw) {
                'letter' => 'Letter',
                'legal' => 'Legal',
                default => 'A4',
            };
            $margin = isset($page['marginMm']) ? (int) $page['marginMm'] : 12;
            $template['page'] = [
                'size' => $size,
                'marginMm' => max(0, min(40, $margin)),
            ];
        }

        if (isset($template['footer']) && is_array($template['footer'])) {
            if (array_key_exists('appendAttachments', $template['footer'])) {
                $template['footer']['appendAttachments'] = filter_var(
                    $template['footer']['appendAttachments'],
                    FILTER_VALIDATE_BOOLEAN,
                );
            }
            if (array_key_exists('showApprovalHistory', $template['footer'])) {
                $template['footer']['showApprovalHistory'] = filter_var(
                    $template['footer']['showApprovalHistory'],
                    FILTER_VALIDATE_BOOLEAN,
                );
            }
            if (array_key_exists('showRequestorSignature', $template['footer'])) {
                $template['footer']['showRequestorSignature'] = filter_var(
                    $template['footer']['showRequestorSignature'],
                    FILTER_VALIDATE_BOOLEAN,
                );
            }
        }

        if (array_key_exists('subsidiary_logo_field', $template)) {
            $field = trim((string) $template['subsidiary_logo_field']);
            $template['subsidiary_logo_field'] = $field !== '' ? substr($field, 0, 80) : 'subsidiary';
        }

        if (array_key_exists('subsidiary_logos', $template)) {
            $template['subsidiary_logos'] = $this->sanitizeSubsidiaryLogos(
                is_array($template['subsidiary_logos']) ? $template['subsidiary_logos'] : [],
            );
        }

        if (array_key_exists('subsidiary_codes', $template)) {
            $template['subsidiary_codes'] = $this->sanitizeSubsidiaryCodes(
                is_array($template['subsidiary_codes']) ? $template['subsidiary_codes'] : [],
            );
        } elseif (isset($template['subsidiary_logos']) && is_array($template['subsidiary_logos'])) {
            $template['subsidiary_codes'] = $this->sanitizeSubsidiaryCodes(array_keys($template['subsidiary_logos']));
        }

        return $template;
    }

    /**
     * Normalize a subsidiary code (e.g. ATC, ADIC, NEWCO).
     * Letters, digits, underscore, hyphen; 2–24 chars.
     */
    public function normalizeSubsidiaryCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if ($code === '' || strlen($code) > 24 || ! preg_match('/^[A-Z0-9][A-Z0-9_-]{0,23}$/', $code)) {
            throw ValidationException::withMessages([
                'code' => [__('Subsidiary code must be 2–24 characters: letters, numbers, _ or -.')],
            ]);
        }

        return $code;
    }

    /**
     * @param  list<mixed>  $codes
     * @return list<string>
     */
    public function sanitizeSubsidiaryCodes(array $codes): array
    {
        $out = [];
        foreach ($codes as $raw) {
            if (! is_string($raw) && ! is_numeric($raw)) {
                continue;
            }
            try {
                $code = $this->normalizeSubsidiaryCode((string) $raw);
            } catch (ValidationException) {
                continue;
            }
            $out[$code] = $code;
            if (count($out) >= 40) {
                break;
            }
        }

        return array_values($out);
    }

    /**
     * @param  array<string, mixed>  $logos
     * @return array<string, string>
     */
    public function sanitizeSubsidiaryLogos(array $logos): array
    {
        $out = [];

        foreach ($logos as $rawCode => $rawValue) {
            try {
                $code = $this->normalizeSubsidiaryCode((string) $rawCode);
            } catch (ValidationException) {
                continue;
            }
            if (! is_string($rawValue)) {
                continue;
            }
            $value = trim($rawValue);
            if ($value === '' || strlen($value) > 512) {
                continue;
            }
            // Allow storage paths or API presentation URLs for this form's subsidiary logos.
            if (
                str_contains($value, '..')
                || (
                    ! str_starts_with($value, '/api/v1/e-approval/forms/')
                    && ! str_contains($value, '/e-approval/forms/')
                    && ! str_starts_with($value, '/storage/')
                )
            ) {
                continue;
            }
            $out[$code] = $value;
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * Keep storage-path logos when the client echoes presentation URLs on Save.
     * Never drop a registered code's logo just because the UI sent API URLs.
     *
     * @param  array<string, mixed>  $existingTemplate
     * @param  array<string, mixed>  $incomingTemplate
     * @return array<string, mixed>
     */
    private function mergeSubsidiaryLogoPersistence(array $existingTemplate, array $incomingTemplate): array
    {
        $existingLogos = $this->sanitizeSubsidiaryLogos(
            is_array($existingTemplate['subsidiary_logos'] ?? null) ? $existingTemplate['subsidiary_logos'] : [],
        );
        $incomingLogosRaw = is_array($incomingTemplate['subsidiary_logos'] ?? null)
            ? $incomingTemplate['subsidiary_logos']
            : [];

        $mergedLogos = $existingLogos;
        foreach ($incomingLogosRaw as $rawCode => $rawValue) {
            try {
                $code = $this->normalizeSubsidiaryCode((string) $rawCode);
            } catch (ValidationException) {
                continue;
            }
            if (! is_string($rawValue)) {
                continue;
            }
            $value = trim($rawValue);
            if ($value === '') {
                continue;
            }
            // Presentation URLs are display-only — keep the stored filesystem path.
            if ($this->isPresentedSubsidiaryLogoUrl($value)) {
                continue;
            }
            $mergedLogos[$code] = $value;
        }

        if (array_key_exists('subsidiary_codes', $incomingTemplate) && is_array($incomingTemplate['subsidiary_codes'])) {
            $codes = $this->sanitizeSubsidiaryCodes($incomingTemplate['subsidiary_codes']);
            foreach (array_keys($mergedLogos) as $code) {
                if (! in_array($code, $codes, true)) {
                    unset($mergedLogos[$code]);
                }
            }
            $incomingTemplate['subsidiary_codes'] = $codes;
        } else {
            $incomingTemplate['subsidiary_codes'] = is_array($existingTemplate['subsidiary_codes'] ?? null)
                ? $existingTemplate['subsidiary_codes']
                : [];
        }

        $incomingTemplate['subsidiary_logos'] = $mergedLogos;

        return $incomingTemplate;
    }

    private function isPresentedSubsidiaryLogoUrl(string $value): bool
    {
        return str_starts_with($value, '/api/v1/e-approval/forms/')
            || (bool) preg_match('#/e-approval/forms/[^/]+/subsidiary-logos/#', $value);
    }

    /**
     * Default subsidiary codes shown in Print options (match FE defaults).
     *
     * @return list<string>
     */
    public function defaultSubsidiaryCodes(): array
    {
        return ['ATC', 'ADIC'];
    }

    /**
     * Merge stored codes + logo keys; seed defaults when the list would otherwise be empty.
     *
     * @param  array<string, mixed>  $template
     * @param  list<string>  $extraCodes
     * @return list<string>
     */
    public function resolveSubsidiaryCodesForPersist(array $template, array $extraCodes = []): array
    {
        $codes = is_array($template['subsidiary_codes'] ?? null) ? $template['subsidiary_codes'] : [];
        $logos = is_array($template['subsidiary_logos'] ?? null) ? $template['subsidiary_logos'] : [];
        $merged = $this->sanitizeSubsidiaryCodes([...$codes, ...array_keys($logos), ...$extraCodes]);

        // Legacy / first-upload: codes list was empty while the UI still showed ATC+ADIC defaults.
        // Union defaults so uploading ATC does not persist codes as [ATC] only and hide ADIC.
        if ($codes === []) {
            $merged = $this->sanitizeSubsidiaryCodes([...$this->defaultSubsidiaryCodes(), ...$merged]);
        }

        return $merged !== [] ? $merged : $this->defaultSubsidiaryCodes();
    }

    /**
     * Persist a subsidiary logo path into the form's pdf_layout template.
     */
    public function setSubsidiaryLogoPath(string $formId, string $code, string $storagePath): array
    {
        $code = $this->normalizeSubsidiaryCode($code);

        $key = $this->formKey($formId);
        $stored = $this->settings->getJson($key) ?? $this->buildDefaultForForm($formId, $this->defaultTemplate());
        $template = is_array($stored['template'] ?? null) ? $stored['template'] : $this->defaultTemplate();
        $logos = is_array($template['subsidiary_logos'] ?? null) ? $template['subsidiary_logos'] : [];
        $logos[$code] = $storagePath;
        $template['subsidiary_logos'] = $this->sanitizeSubsidiaryLogos($logos);
        // Keep existing codes (incl. ATC/ADIC defaults). Never collapse to only the uploaded code.
        $template['subsidiary_codes'] = $this->resolveSubsidiaryCodesForPersist($template, [$code]);
        if (! isset($template['subsidiary_logo_field']) || trim((string) $template['subsidiary_logo_field']) === '') {
            $template['subsidiary_logo_field'] = 'subsidiary';
        }
        $stored['template'] = $this->sanitizeTemplate($template);
        $stored['updated_at'] = now()->toIso8601String();
        $this->settings->setJson($key, $stored);

        $this->syncSubsidiaryFieldChoices($formId, $template);

        return $stored['template']['subsidiary_logos'];
    }

    public function clearSubsidiaryLogoPath(string $formId, string $code, bool $removeCode = false): void
    {
        $code = $this->normalizeSubsidiaryCode($code);
        $key = $this->formKey($formId);
        $stored = $this->settings->getJson($key);
        if ($stored === null || ! is_array($stored['template'] ?? null)) {
            return;
        }
        $template = $stored['template'];
        $logos = is_array($template['subsidiary_logos'] ?? null) ? $template['subsidiary_logos'] : [];
        unset($logos[$code]);
        $template['subsidiary_logos'] = $this->sanitizeSubsidiaryLogos($logos);
        if ($removeCode) {
            $codes = is_array($template['subsidiary_codes'] ?? null) ? $template['subsidiary_codes'] : array_keys($logos);
            $template['subsidiary_codes'] = $this->sanitizeSubsidiaryCodes(
                array_values(array_filter(
                    $codes,
                    static fn ($c): bool => strtoupper(trim((string) $c)) !== $code,
                )),
            );
        }
        $stored['template'] = $this->sanitizeTemplate($template);
        $stored['updated_at'] = now()->toIso8601String();
        $this->settings->setJson($key, $stored);

        $this->syncSubsidiaryFieldChoices($formId, $stored['template']);
    }

    /**
     * Register a subsidiary code (optionally without a logo yet).
     *
     * @return list<string>
     */
    public function registerSubsidiaryCode(string $formId, string $code): array
    {
        $code = $this->normalizeSubsidiaryCode($code);
        $key = $this->formKey($formId);
        $stored = $this->settings->getJson($key) ?? $this->buildDefaultForForm($formId, $this->defaultTemplate());
        $template = is_array($stored['template'] ?? null) ? $stored['template'] : $this->defaultTemplate();
        $codes = is_array($template['subsidiary_codes'] ?? null) ? $template['subsidiary_codes'] : [];
        $logos = is_array($template['subsidiary_logos'] ?? null) ? $template['subsidiary_logos'] : [];
        $template['subsidiary_codes'] = $this->resolveSubsidiaryCodesForPersist(
            ['subsidiary_codes' => $codes, 'subsidiary_logos' => $logos],
            [$code],
        );        if (! isset($template['subsidiary_logo_field']) || trim((string) $template['subsidiary_logo_field']) === '') {
            $template['subsidiary_logo_field'] = 'subsidiary';
        }
        $stored['template'] = $this->sanitizeTemplate($template);
        $stored['updated_at'] = now()->toIso8601String();
        $this->settings->setJson($key, $stored);

        $this->syncSubsidiaryFieldChoices($formId, $stored['template']);

        return $stored['template']['subsidiary_codes'] ?? [$code];
    }

    /**
     * Remove a subsidiary code and its logo from the form layout.
     *
     * @return list<string>
     */
    public function unregisterSubsidiaryCode(string $formId, string $code): array
    {
        $code = $this->normalizeSubsidiaryCode($code);
        $key = $this->formKey($formId);
        $stored = $this->settings->getJson($key);

        if ($stored === null || ! is_array($stored['template'] ?? null)) {
            $stored = $this->buildDefaultForForm($formId, $this->defaultTemplate());
            $template = is_array($stored['template'] ?? null) ? $stored['template'] : $this->defaultTemplate();
            $seed = $this->presentSubsidiaryCodes($template);
            if ($seed === []) {
                $seed = $this->defaultSubsidiaryCodes();
            }
            $template['subsidiary_codes'] = $this->sanitizeSubsidiaryCodes(
                array_values(array_filter(
                    $seed,
                    static fn (string $c): bool => $c !== $code,
                )),
            );
            $template['subsidiary_logos'] = $this->sanitizeSubsidiaryLogos(
                is_array($template['subsidiary_logos'] ?? null) ? $template['subsidiary_logos'] : [],
            );
            unset($template['subsidiary_logos'][$code]);
            if (! isset($template['subsidiary_logo_field']) || trim((string) $template['subsidiary_logo_field']) === '') {
                $template['subsidiary_logo_field'] = 'subsidiary';
            }
            $stored['template'] = $this->sanitizeTemplate($template);
            $stored['updated_at'] = now()->toIso8601String();
            $this->settings->setJson($key, $stored);
            $this->syncSubsidiaryFieldChoices($formId, $stored['template']);

            return $this->presentSubsidiaryCodes($stored['template']);
        }

        $this->clearSubsidiaryLogoPath($formId, $code, true);

        $layout = $this->show($formId);
        $template = is_array($layout['template'] ?? null) ? $layout['template'] : [];

        return $this->presentSubsidiaryCodes($template);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, string> presented API URLs keyed by subsidiary code
     */
    public function presentSubsidiaryLogoUrls(string $formId, array $template): array
    {
        $logos = is_array($template['subsidiary_logos'] ?? null) ? $template['subsidiary_logos'] : [];
        $presented = [];
        foreach ($this->sanitizeSubsidiaryLogos($logos) as $code => $value) {
            $presented[$code] = '/api/v1/e-approval/forms/'.$formId.'/subsidiary-logos/'.$code;
        }

        return $presented;
    }

    /**
     * @param  array<string, mixed>  $template
     * @return list<string>
     */
    public function presentSubsidiaryCodes(array $template): array
    {
        return $this->resolveSubsidiaryCodesForPersist($template);
    }

    /**
     * Keep the form's Subsidiary select choices aligned with configured codes.
     *
     * @param  array<string, mixed>  $template
     */
    public function syncSubsidiaryFieldChoices(string $formId, array $template): void
    {
        if ($formId === 'global-default') {
            return;
        }

        $fieldName = trim((string) ($template['subsidiary_logo_field'] ?? 'subsidiary'));
        if ($fieldName === '') {
            $fieldName = 'subsidiary';
        }

        $codes = $this->presentSubsidiaryCodes($template);
        if ($codes === []) {
            $codes = $this->defaultSubsidiaryCodes();
        }

        $form = EApprovalForm::query()->with('fields')->find($formId);
        if ($form === null) {
            return;
        }

        $field = $form->fields->first(
            static fn ($f): bool => strcasecmp((string) $f->name, $fieldName) === 0,
        );
        if ($field === null || strtolower((string) $field->type) !== 'select') {
            return;
        }

        $options = is_array($field->options) ? $field->options : [];
        $options['choices'] = array_map(
            static fn (string $code): array => ['value' => $code, 'label' => $code],
            $codes,
        );
        $field->options = $options;
        $field->save();
    }

    private function formKey(string $formId): string
    {
        return 'pdf_layout_form_'.$formId;
    }
}
