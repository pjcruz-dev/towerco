<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynEntityHook;
use App\Modules\DynamicEntities\Support\DynEntityHookDsl;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DynEntityHookService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $entitySlug = null): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $this->ensureBuiltinHooks();

        $q = DynEntityHook::query()->orderBy('entity_slug')->orderBy('sort_order')->orderBy('name');
        if ($entitySlug !== null && $entitySlug !== '') {
            $q->where('entity_slug', $entitySlug);
        }

        return $q->get()->map(fn (DynEntityHook $h): array => $this->present($h))->all();
    }

    /**
     * @return array{
     *   total: int,
     *   active: int,
     *   inactive: int,
     *   by_event: array<string, array{active: int, inactive: int}>
     * }
     */
    public function stats(): array
    {
        if (! $this->tableReady()) {
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'by_event' => [],
            ];
        }

        $this->ensureBuiltinHooks();

        $rows = DynEntityHook::query()->get(['is_active', 'events']);
        $byEvent = [];
        foreach (DynEntityHookDsl::EVENTS as $event) {
            $byEvent[$event] = ['active' => 0, 'inactive' => 0];
        }

        $active = 0;
        $inactive = 0;
        foreach ($rows as $row) {
            if ($row->is_active) {
                $active++;
            } else {
                $inactive++;
            }
            $events = is_array($row->events) ? $row->events : [];
            foreach ($events as $event) {
                $key = (string) $event;
                if (! isset($byEvent[$key])) {
                    $byEvent[$key] = ['active' => 0, 'inactive' => 0];
                }
                if ($row->is_active) {
                    $byEvent[$key]['active']++;
                } else {
                    $byEvent[$key]['inactive']++;
                }
            }
        }

        return [
            'total' => $rows->count(),
            'active' => $active,
            'inactive' => $inactive,
            'by_event' => $byEvent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(DynEntityHook $hook): array
    {
        return $this->present($hook);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, TenantUser $actor): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => ['Hook name is required.']]);
        }

        $entitySlug = trim((string) ($data['entity_slug'] ?? ''));
        if ($entitySlug === '') {
            throw ValidationException::withMessages(['entity_slug' => ['Choose a target entity.']]);
        }
        $this->assertEntityExists($entitySlug);

        $events = DynEntityHookDsl::normalizeEvents($data['events'] ?? []);
        if ($events === []) {
            throw ValidationException::withMessages(['events' => ['Select at least one lifecycle event.']]);
        }

        $definition = DynEntityHookDsl::normalize(
            is_array($data['definition_json'] ?? null) ? $data['definition_json'] : null,
        );
        if ($definition['actions'] === []) {
            throw ValidationException::withMessages(['definition_json' => ['Add at least one action.']]);
        }

        $hook = DynEntityHook::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug((string) ($data['slug'] ?? ''), $name),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'entity_slug' => $entitySlug,
            'events' => $events,
            'definition_json' => $definition,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => (string) $actor->id,
            'updated_by' => (string) $actor->id,
        ]);

        return $this->present($hook);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(DynEntityHook $hook, array $data, TenantUser $actor): array
    {
        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => ['Hook name is required.']]);
            }
            $hook->name = $name;
        }
        if (array_key_exists('description', $data)) {
            $desc = trim((string) ($data['description'] ?? ''));
            $hook->description = $desc !== '' ? $desc : null;
        }
        if (array_key_exists('entity_slug', $data)) {
            $entitySlug = trim((string) $data['entity_slug']);
            $this->assertEntityExists($entitySlug);
            $hook->entity_slug = $entitySlug;
        }
        if (array_key_exists('events', $data)) {
            $events = DynEntityHookDsl::normalizeEvents($data['events']);
            if ($events === []) {
                throw ValidationException::withMessages(['events' => ['Select at least one lifecycle event.']]);
            }
            $hook->events = $events;
        }
        if (array_key_exists('definition_json', $data)) {
            $definition = DynEntityHookDsl::normalize(
                is_array($data['definition_json']) ? $data['definition_json'] : null,
            );
            if ($definition['actions'] === []) {
                throw ValidationException::withMessages(['definition_json' => ['Add at least one action.']]);
            }
            $hook->definition_json = $definition;
        }
        if (array_key_exists('is_active', $data)) {
            $hook->is_active = (bool) $data['is_active'];
        }
        if (array_key_exists('sort_order', $data)) {
            $hook->sort_order = (int) $data['sort_order'];
        }

        $hook->updated_by = (string) $actor->id;
        $hook->save();

        return $this->present($hook->fresh() ?? $hook);
    }

    public function destroy(DynEntityHook $hook): void
    {
        $hook->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function toggle(DynEntityHook $hook, TenantUser $actor): array
    {
        $hook->is_active = ! (bool) $hook->is_active;
        $hook->updated_by = (string) $actor->id;
        $hook->save();

        return $this->present($hook);
    }

    /**
     * @return list<DynEntityHook>
     */
    public function activeFor(string $entitySlug, string $event): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        return DynEntityHook::query()
            ->where('entity_slug', $entitySlug)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(function (DynEntityHook $hook) use ($event): bool {
                $events = is_array($hook->events) ? $hook->events : [];

                return in_array($event, $events, true);
            })
            ->values()
            ->all();
    }

    public function ensureBuiltinHooks(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        $slug = 'at-029-company-id-mirrors-subsidiary-id';
        if (DynEntityHook::query()->where('slug', $slug)->exists()) {
            return;
        }

        // Only seed when the entity exists (finance pack present).
        if (! DynEntity::query()->where('slug', 'bank_accounts')->exists()) {
            return;
        }

        DynEntityHook::query()->create([
            'name' => 'AT-029 — company_id mirrors subsidiary_id (the AT-023 contract)',
            'slug' => $slug,
            'description' => 'Keep company_id in sync with subsidiary_id on bank account create/update.',
            'entity_slug' => 'bank_accounts',
            'events' => ['before_create', 'before_update'],
            'definition_json' => DynEntityHookDsl::normalize([
                'when' => [
                    ['field' => 'subsidiary_id', 'op' => 'filled'],
                ],
                'actions' => [
                    ['type' => 'mirror_field', 'from' => 'subsidiary_id', 'to' => 'company_id'],
                ],
            ]),
            'is_active' => true,
            'sort_order' => 10,
            'created_by' => null,
            'updated_by' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DynEntityHook $hook): array
    {
        $definition = DynEntityHookDsl::normalize(
            is_array($hook->definition_json) ? $hook->definition_json : null,
        );

        return [
            'id' => (string) $hook->id,
            'name' => (string) $hook->name,
            'slug' => (string) $hook->slug,
            'description' => $hook->description,
            'entity_slug' => (string) $hook->entity_slug,
            'events' => DynEntityHookDsl::normalizeEvents($hook->events ?? []),
            'definition_json' => $definition,
            'is_active' => (bool) $hook->is_active,
            'sort_order' => (int) $hook->sort_order,
            'created_at' => optional($hook->created_at)?->toIso8601String(),
            'updated_at' => optional($hook->updated_at)?->toIso8601String(),
        ];
    }

    private function assertEntityExists(string $entitySlug): void
    {
        if (! DynEntity::query()->where('slug', $entitySlug)->exists()) {
            throw ValidationException::withMessages([
                'entity_slug' => ['Entity “'.$entitySlug.'” was not found.'],
            ]);
        }
    }

    private function uniqueSlug(string $requested, string $name): string
    {
        $base = Str::slug($requested !== '' ? $requested : $name);
        if ($base === '') {
            $base = 'hook';
        }
        $base = Str::limit($base, 140, '');
        $slug = $base;
        $i = 2;
        while (DynEntityHook::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 130, '').'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::connection('tenant')->hasTable('dyn_entity_hooks');
        } catch (\Throwable) {
            return false;
        }
    }
}
