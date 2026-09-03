<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Support\DynEntityWorkflowActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live metadata for the Manage REST API developer guide (workflows, schema map, etc.).
 */
class IntegrationApiDocsMetaController extends AbstractApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('api_keys:manage'), 403);

        $entities = DynEntity::query()
            ->where('is_active', true)
            ->with(['fields' => static fn ($q) => $q->orderBy('field_order')->orderBy('name')])
            ->orderBy('name')
            ->get();

        $workflows = [];
        $schemas = [];

        foreach ($entities as $entity) {
            foreach (DynEntityWorkflowActions::forEntity($entity) as $action) {
                $workflows[] = [
                    'entity_slug' => (string) $entity->slug,
                    'entity_name' => (string) $entity->name,
                    'action_id' => (string) ($action['id'] ?? ''),
                    'label' => (string) ($action['label'] ?? $action['id'] ?? ''),
                    'from_status' => (string) ($action['from_status'] ?? ''),
                    'to_status' => (string) ($action['to_status'] ?? ''),
                    'variant' => (string) ($action['variant'] ?? 'default'),
                ];
            }

            $fields = $entity->fields
                ->filter(static fn (DynField $f): bool => ! (bool) $f->is_system_field
                    || in_array((string) $f->name, ['status', 'title'], true))
                ->values()
                ->map(static fn (DynField $f): array => [
                    'name' => (string) $f->name,
                    'label' => (string) ($f->label ?: $f->name),
                    'type' => (string) $f->type,
                    'required' => (bool) $f->is_required,
                    'filterable' => (bool) $f->is_filterable,
                ])
                ->all();

            $schemas[] = [
                'slug' => (string) $entity->slug,
                'name' => (string) $entity->name,
                'module_pack' => (string) ($entity->module_pack ?? ''),
                'field_count' => count($fields),
                'fields' => $fields,
            ];
        }

        return $this->ok([
            'integration_prefix' => '/integration',
            'entity_count' => $entities->count(),
            'workflows' => $workflows,
            'schemas' => $schemas,
            'sample_entity_slug' => $entities->firstWhere('slug', 'tower_sites')?->slug
                ?? $entities->first()?->slug
                ?? 'tower_sites',
        ]);
    }
}
