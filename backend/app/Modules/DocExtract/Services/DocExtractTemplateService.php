<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use App\Modules\DocExtract\Models\DocExtractTemplate;
use App\Modules\DocExtract\Support\DocExtractFieldTypes;
use App\Modules\DocExtract\Support\DocExtractTemplateStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocExtractTemplateService
{
    public function __construct(
        private readonly DocExtractAuditLogger $audit,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $status = null): array
    {
        $query = DocExtractTemplate::query()->orderBy('name');

        if ($status !== null && $status !== '') {
            $query->where('status', DocExtractTemplateStatus::normalize($status));
        }

        return $query
            ->get()
            ->map(fn (DocExtractTemplate $template): array => $this->asRow($template))
            ->all();
    }

    public function findOrFail(string $id): DocExtractTemplate
    {
        return DocExtractTemplate::query()->findOrFail($id);
    }

    public function findPublishedOrFail(string $id): DocExtractTemplate
    {
        $template = $this->findOrFail($id);
        if (! $template->isPublished()) {
            throw ValidationException::withMessages([
                'template_id' => [__('Only published templates can be used for extraction. Publish the template first, or use auto-detect.')],
            ]);
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, TenantUser $actor): DocExtractTemplate
    {
        $fields = $this->normalizeFields($data['fields'] ?? []);
        $status = array_key_exists('status', $data)
            ? DocExtractTemplateStatus::normalize(is_string($data['status'] ?? null) ? $data['status'] : null)
            : DocExtractTemplateStatus::DRAFT;

        $template = DocExtractTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'name' => trim((string) $data['name']),
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'fields' => $fields,
            'status' => $status,
            'created_by_id' => $actor->id,
        ]);

        $this->audit->record(
            action: 'template.created',
            summary: __('DocExtract template ":name" created.', ['name' => $template->name]),
            entityType: 'template',
            entityId: (string) $template->id,
            entityLabel: (string) $template->name,
            actor: $actor,
            changes: [
                'status' => ['from' => null, 'to' => $template->status],
                'field_count' => ['from' => null, 'to' => count($fields)],
            ],
        );

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DocExtractTemplate $template, array $data, ?TenantUser $actor = null): DocExtractTemplate
    {
        $previousStatus = DocExtractTemplateStatus::normalize($template->status);
        $previousName = (string) $template->name;
        $previousFieldCount = is_array($template->fields) ? count($template->fields) : 0;

        if (array_key_exists('name', $data)) {
            $template->name = trim((string) $data['name']);
        }
        if (array_key_exists('description', $data)) {
            $description = $data['description'];
            $template->description = $description === null || $description === ''
                ? null
                : trim((string) $description);
        }
        if (array_key_exists('fields', $data)) {
            $template->fields = $this->normalizeFields($data['fields']);
        }
        if (array_key_exists('status', $data)) {
            $template->status = DocExtractTemplateStatus::normalize(
                is_string($data['status'] ?? null) ? $data['status'] : null,
            );
        }
        $template->save();
        $fresh = $template->fresh() ?? $template;
        $nextStatus = DocExtractTemplateStatus::normalize($fresh->status);

        $action = 'template.updated';
        if (array_key_exists('status', $data) && $previousStatus !== $nextStatus) {
            $action = $nextStatus === DocExtractTemplateStatus::PUBLISHED
                ? 'template.published'
                : 'template.unpublished';
        }

        $this->audit->record(
            action: $action,
            summary: __('DocExtract template ":name" updated.', ['name' => $fresh->name]),
            entityType: 'template',
            entityId: (string) $fresh->id,
            entityLabel: (string) $fresh->name,
            actor: $actor,
            changes: [
                'name' => ['from' => $previousName, 'to' => (string) $fresh->name],
                'status' => ['from' => $previousStatus, 'to' => $nextStatus],
                'field_count' => [
                    'from' => $previousFieldCount,
                    'to' => is_array($fresh->fields) ? count($fresh->fields) : 0,
                ],
            ],
        );

        return $fresh;
    }

    public function delete(DocExtractTemplate $template, ?TenantUser $actor = null): void
    {
        $id = (string) $template->id;
        $name = (string) $template->name;
        $template->delete();

        $this->audit->record(
            action: 'template.deleted',
            summary: __('DocExtract template ":name" deleted.', ['name' => $name]),
            entityType: 'template',
            entityId: $id,
            entityLabel: $name,
            actor: $actor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function asRow(DocExtractTemplate $template): array
    {
        return [
            'id' => (string) $template->id,
            'name' => (string) $template->name,
            'description' => $template->description,
            'status' => DocExtractTemplateStatus::normalize($template->status),
            'fields' => is_array($template->fields) ? array_values($template->fields) : [],
            'created_at' => optional($template->created_at)?->toIso8601String(),
            'updated_at' => optional($template->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  mixed  $fields
     * @return list<array{key: string, label: string, type: string, description: string|null, hint: string|null, columns?: list<array{key: string, label: string, type: string, description: string|null}>}>
     */
    private function normalizeFields(mixed $fields): array
    {
        if (! is_array($fields) || $fields === []) {
            throw ValidationException::withMessages([
                'fields' => [__('Add at least one field to the template.')],
            ]);
        }

        $normalized = [];
        $seenKeys = [];
        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = Str::slug(trim((string) ($field['key'] ?? '')), '_');
            if ($key === '') {
                $key = Str::slug(trim((string) ($field['label'] ?? 'field_'.($index + 1))), '_');
            }
            if ($key === '') {
                throw ValidationException::withMessages([
                    'fields' => [__('Each field needs a key or label.')],
                ]);
            }
            if (isset($seenKeys[$key])) {
                throw ValidationException::withMessages([
                    'fields' => [__('Duplicate field key: :key', ['key' => $key])],
                ]);
            }
            $seenKeys[$key] = true;
            $label = trim((string) ($field['label'] ?? $key));
            $type = DocExtractFieldTypes::normalize((string) ($field['type'] ?? 'text'));
            $description = trim((string) ($field['description'] ?? ''));
            $hint = trim((string) ($field['hint'] ?? ''));

            $row = [
                'key' => $key,
                'label' => $label !== '' ? $label : $key,
                'type' => $type,
                'description' => $description !== '' ? mb_substr($description, 0, 500) : null,
                'hint' => $hint !== '' ? $hint : null,
            ];

            if ($type === 'table') {
                $row['columns'] = $this->normalizeTableColumns($field['columns'] ?? []);
            }

            $normalized[] = $row;
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'fields' => [__('Add at least one field to the template.')],
            ]);
        }

        return $normalized;
    }

    /**
     * @param  mixed  $columns
     * @return list<array{key: string, label: string, type: string, description: string|null}>
     */
    private function normalizeTableColumns(mixed $columns): array
    {
        if (! is_array($columns) || $columns === []) {
            throw ValidationException::withMessages([
                'fields' => [__('Table fields need at least one nested column.')],
            ]);
        }

        $normalized = [];
        $seenKeys = [];
        foreach ($columns as $index => $column) {
            if (! is_array($column)) {
                continue;
            }
            $key = Str::slug(trim((string) ($column['key'] ?? '')), '_');
            if ($key === '') {
                $key = Str::slug(trim((string) ($column['label'] ?? 'column_'.($index + 1))), '_');
            }
            if ($key === '' || isset($seenKeys[$key])) {
                continue;
            }
            $seenKeys[$key] = true;
            $label = trim((string) ($column['label'] ?? $key));
            $description = trim((string) ($column['description'] ?? ''));
            $normalized[] = [
                'key' => $key,
                'label' => $label !== '' ? $label : $key,
                'type' => DocExtractFieldTypes::normalizeTableColumnType((string) ($column['type'] ?? 'text')),
                'description' => $description !== '' ? mb_substr($description, 0, 500) : null,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'fields' => [__('Table fields need at least one nested column.')],
            ]);
        }

        if (count($normalized) > 20) {
            throw ValidationException::withMessages([
                'fields' => [__('A table field may include at most 20 columns.')],
            ]);
        }

        return $normalized;
    }
}
