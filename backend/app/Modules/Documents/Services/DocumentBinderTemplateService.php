<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Data\DocumentBinderTemplateDefaults;
use App\Modules\Documents\Models\DocumentBinderTemplate;
use App\Modules\Documents\Support\DocumentBinderTemplateValidator;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;

final class DocumentBinderTemplateService
{
    public function __construct(
        private readonly DocumentBinderTemplateValidator $validator,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function effectiveTree(): array
    {
        $custom = DocumentBinderTemplate::query()->first();
        if ($custom instanceof DocumentBinderTemplate && is_array($custom->tree_json) && $custom->tree_json !== []) {
            return $custom->tree_json;
        }

        return DocumentBinderTemplateDefaults::tree();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $custom = DocumentBinderTemplate::query()->with('updatedBy:id,name')->first();
        $tree = $this->effectiveTree();

        if ($custom instanceof DocumentBinderTemplate) {
            return [
                'source' => 'tenant_custom',
                'editable' => true,
                'tree' => $tree,
                'updated_at' => $custom->updated_at?->toIso8601String(),
                'updated_by' => $custom->updatedBy ? [
                    'id' => (string) $custom->updatedBy->id,
                    'name' => $custom->updatedBy->name,
                ] : null,
                'note' => __('Custom binder template for new site binders. Existing site folders are unchanged.'),
            ];
        }

        return [
            'source' => 'platform_default',
            'editable' => true,
            'tree' => $tree,
            'updated_at' => null,
            'updated_by' => null,
            'note' => __('Customize folder labels and structure for new site binders. Existing site folders are unchanged.'),
        ];
    }

    /**
     * @param  array<int, mixed>  $tree
     */
    public function update(array $tree, TenantUser $actor): array
    {
        $validated = $this->validator->validate($tree);

        $record = DocumentBinderTemplate::query()->first();
        if ($record === null) {
            $record = DocumentBinderTemplate::query()->create([
                'id' => (string) Str::uuid(),
                'tree_json' => $validated,
                'updated_by_id' => $actor->id,
            ]);
        } else {
            $record->tree_json = $validated;
            $record->updated_by_id = $actor->id;
            $record->save();
        }

        return $this->payload();
    }

    public function resetToDefault(TenantUser $actor): array
    {
        DocumentBinderTemplate::query()->delete();

        return $this->payload();
    }
}
