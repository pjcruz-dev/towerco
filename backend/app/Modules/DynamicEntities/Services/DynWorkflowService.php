<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynWorkflow;
use App\Modules\DynamicEntities\Support\DynEntityWorkflowActions;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DynWorkflowService
{
    /** @var list<string> */
    public const TRIGGER_MODES = ['manual', 'on_create', 'on_update'];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $entitySlug = null): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $q = DynWorkflow::query()->orderBy('sort_order')->orderBy('name');
        if ($entitySlug !== null && $entitySlug !== '') {
            $q->where('entity_slug', $entitySlug);
        }

        return $q->get()->map(fn (DynWorkflow $w): array => $this->present($w, false))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(DynWorkflow $workflow): array
    {
        return $this->present($workflow, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, TenantUser $actor): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => ['Workflow name is required.']]);
        }

        $entitySlug = trim((string) ($data['entity_slug'] ?? ''));
        if ($entitySlug === '') {
            throw ValidationException::withMessages(['entity_slug' => ['Choose a target entity.']]);
        }
        $this->assertEntityExists($entitySlug);

        $trigger = $this->normalizeTrigger((string) ($data['trigger_mode'] ?? 'manual'));
        $statusField = trim((string) ($data['status_field'] ?? 'status')) ?: 'status';
        $statusMatches = $this->normalizeStatusMatches($data['status_matches'] ?? []);
        $roleIds = $this->normalizeRoleIds($data['role_ids'] ?? []);

        $slug = $this->uniqueSlug((string) ($data['slug'] ?? ''), $name);
        $definition = $this->seedDefinition(
            $slug,
            $name,
            $statusField,
            $statusMatches,
            $roleIds,
            is_array($data['definition_json'] ?? null) ? $data['definition_json'] : null,
        );

        $workflow = DynWorkflow::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'entity_slug' => $entitySlug,
            'trigger_mode' => $trigger,
            'status_field' => $statusField,
            'status_matches' => $statusMatches,
            'role_ids' => $roleIds,
            'definition_json' => $definition,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => (string) $actor->id,
            'updated_by' => (string) $actor->id,
        ]);

        return $this->present($workflow, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(DynWorkflow $workflow, array $data, TenantUser $actor): array
    {
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => ['Workflow name is required.']]);
            }
            $workflow->name = $name;
        }
        if (array_key_exists('description', $data)) {
            $desc = trim((string) ($data['description'] ?? ''));
            $workflow->description = $desc !== '' ? $desc : null;
        }
        if (array_key_exists('entity_slug', $data)) {
            $entitySlug = trim((string) $data['entity_slug']);
            $this->assertEntityExists($entitySlug);
            $workflow->entity_slug = $entitySlug;
        }
        if (array_key_exists('trigger_mode', $data)) {
            $workflow->trigger_mode = $this->normalizeTrigger((string) $data['trigger_mode']);
        }
        if (array_key_exists('status_field', $data)) {
            $workflow->status_field = trim((string) $data['status_field']) ?: 'status';
        }
        if (array_key_exists('status_matches', $data)) {
            $workflow->status_matches = $this->normalizeStatusMatches($data['status_matches']);
        }
        if (array_key_exists('role_ids', $data)) {
            $workflow->role_ids = $this->normalizeRoleIds($data['role_ids']);
        }
        if (array_key_exists('is_active', $data)) {
            $workflow->is_active = (bool) $data['is_active'];
        }
        if (array_key_exists('sort_order', $data)) {
            $workflow->sort_order = (int) $data['sort_order'];
        }
        if (array_key_exists('definition_json', $data) && is_array($data['definition_json'])) {
            $workflow->definition_json = $this->normalizeDefinition(
                $data['definition_json'],
                (string) $workflow->slug,
                (string) $workflow->name,
                (string) $workflow->status_field,
                is_array($workflow->status_matches) ? $workflow->status_matches : [],
                is_array($workflow->role_ids) ? $workflow->role_ids : [],
            );
        } else {
            // Keep WHEN / roles in sync with top-level constraint fields.
            $workflow->definition_json = $this->normalizeDefinition(
                is_array($workflow->definition_json) ? $workflow->definition_json : [],
                (string) $workflow->slug,
                (string) $workflow->name,
                (string) $workflow->status_field,
                is_array($workflow->status_matches) ? $workflow->status_matches : [],
                is_array($workflow->role_ids) ? $workflow->role_ids : [],
            );
        }

        $workflow->updated_by = (string) $actor->id;
        $workflow->save();

        return $this->present($workflow, true);
    }

    public function destroy(DynWorkflow $workflow): void
    {
        $workflow->delete();
    }

    /**
     * Manual-button actions for an entity (merged into DynEntityWorkflowActions).
     *
     * @return list<array<string, mixed>>
     */
    public function manualActionsForEntity(string $entitySlug): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        return DynWorkflow::query()
            ->where('entity_slug', $entitySlug)
            ->where('trigger_mode', 'manual')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DynWorkflow $w): array => $this->toActionDef($w))
            ->values()
            ->all();
    }

    /**
     * @return list<DynWorkflow>
     */
    public function autoWorkflows(string $entitySlug, string $triggerMode): array
    {
        if (! $this->tableReady()) {
            return [];
        }
        if (! in_array($triggerMode, ['on_create', 'on_update'], true)) {
            return [];
        }

        return DynWorkflow::query()
            ->where('entity_slug', $entitySlug)
            ->where('trigger_mode', $triggerMode)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toActionDef(DynWorkflow $workflow): array
    {
        $def = $this->normalizeDefinition(
            is_array($workflow->definition_json) ? $workflow->definition_json : [],
            (string) $workflow->slug,
            (string) $workflow->name,
            (string) $workflow->status_field,
            is_array($workflow->status_matches) ? $workflow->status_matches : [],
            is_array($workflow->role_ids) ? $workflow->role_ids : [],
        );

        // Ensure id stable for matrix / API.
        $def['id'] = (string) $workflow->slug;
        $def['label'] = (string) ($def['label'] ?? $workflow->name);
        $def['managed_workflow_id'] = (string) $workflow->id;

        return $def;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('dyn_workflows');
        } catch (\Throwable) {
            return false;
        }
    }

    private function assertEntityExists(string $slug): void
    {
        $exists = DynEntity::query()->where('slug', $slug)->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['entity_slug' => ['Entity not found.']]);
        }
    }

    private function normalizeTrigger(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (! in_array($mode, self::TRIGGER_MODES, true)) {
            throw ValidationException::withMessages([
                'trigger_mode' => ['Trigger mode must be manual, on_create, or on_update.'],
            ]);
        }

        return $mode;
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function normalizeStatusMatches(mixed $raw): array
    {
        if (is_string($raw)) {
            $parts = preg_split('/\s*,\s*/', $raw) ?: [];
            $raw = $parts;
        }
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $s = trim((string) $v);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private function normalizeRoleIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $s = trim((string) $v);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }

    private function uniqueSlug(string $slug, string $name): string
    {
        $base = Str::slug($slug !== '' ? $slug : $name);
        if ($base === '') {
            $base = 'workflow';
        }
        if (strlen($base) > 140) {
            $base = substr($base, 0, 140);
        }
        $candidate = $base;
        $i = 2;
        while (DynWorkflow::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * @param  list<string>  $statusMatches
     * @param  list<string>  $roleIds
     * @param  array<string, mixed>|null  $incoming
     * @return array<string, mixed>
     */
    private function seedDefinition(
        string $slug,
        string $name,
        string $statusField,
        array $statusMatches,
        array $roleIds,
        ?array $incoming,
    ): array {
        return $this->normalizeDefinition($incoming ?? [], $slug, $name, $statusField, $statusMatches, $roleIds);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $statusMatches
     * @param  list<string>  $roleIds
     * @return array<string, mixed>
     */
    private function normalizeDefinition(
        array $raw,
        string $slug,
        string $name,
        string $statusField,
        array $statusMatches,
        array $roleIds,
    ): array {
        $when = [];
        if ($statusMatches !== []) {
            // Multi-status: runtime matchesWhen is AND across conditions; use first only in when
            // and store all matches for OR evaluation via special helper — use OR by expanding
            // into a single status condition that matchesWhen can't OR. So we store multiple
            // when entries won't work as OR. Instead store status_matches on definition and
            // one when for the first, plus custom handling in available/auto.
            // Practical approach: put each match as separate "or" group in definition meta,
            // and use when with first value; DynEntityWorkflowActions::matchesWhen is AND.
            // For multi-status OR, we override matching in auto runner and in forEntity filter.
            foreach ($statusMatches as $match) {
                $when[] = ['field' => $statusField, 'op' => 'eq', 'value' => $match];
                break; // single when; OR handled via status_matches meta
            }
        } elseif (isset($raw['when']) && is_array($raw['when'])) {
            $when = $raw['when'];
        }

        $from = (string) ($raw['from_status'] ?? ($statusMatches[0] ?? ''));
        $to = (string) ($raw['to_status'] ?? '');

        $normalized = DynEntityWorkflowActions::normalizeButtons([[
            'id' => $slug,
            'label' => (string) ($raw['label'] ?? $name),
            'from_status' => $from,
            'to_status' => $to,
            'confirm' => $raw['confirm'] ?? null,
            'variant' => $raw['variant'] ?? 'default',
            'role_ids' => $roleIds,
            'when' => $when,
            'loads' => $raw['loads'] ?? [],
            'then_updates' => $raw['then_updates'] ?? [],
            'creates' => $raw['creates'] ?? [],
            'emails' => $raw['emails'] ?? [],
        ]]);

        $def = $normalized[0] ?? [
            'id' => $slug,
            'label' => $name,
            'from_status' => $from,
            'to_status' => $to,
            'confirm' => null,
            'variant' => 'default',
            'role_ids' => $roleIds,
            'when' => $when,
            'loads' => [],
            'then_updates' => [],
            'creates' => [],
            'emails' => [],
        ];

        $def['status_matches'] = $statusMatches;
        $def['status_field'] = $statusField;
        $def['role_ids'] = $roleIds;

        return $def;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DynWorkflow $workflow, bool $withDefinition): array
    {
        $payload = [
            'id' => (string) $workflow->id,
            'name' => (string) $workflow->name,
            'slug' => (string) $workflow->slug,
            'description' => $workflow->description,
            'entity_slug' => (string) $workflow->entity_slug,
            'trigger_mode' => (string) $workflow->trigger_mode,
            'status_field' => (string) $workflow->status_field,
            'status_matches' => is_array($workflow->status_matches) ? array_values($workflow->status_matches) : [],
            'role_ids' => is_array($workflow->role_ids) ? array_values($workflow->role_ids) : [],
            'is_active' => (bool) $workflow->is_active,
            'sort_order' => (int) $workflow->sort_order,
            'created_at' => optional($workflow->created_at)?->toIso8601String(),
            'updated_at' => optional($workflow->updated_at)?->toIso8601String(),
        ];

        if ($withDefinition) {
            $payload['definition_json'] = is_array($workflow->definition_json) ? $workflow->definition_json : [];
            $payload['action'] = $this->toActionDef($workflow);
        }

        return $payload;
    }
}
