<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\AppMenuGroup;
use App\Modules\Platform\Models\AppMenuSetting;
use App\Modules\Platform\Models\AppMenuTile;
use App\Modules\Platform\Support\AppMenuGroupDefaults;
use App\Modules\Platform\Support\AppMenuTileDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AppMenuService
{
    /**
     * @return array{settings: array{grid_columns: int}, groups: list<array<string, mixed>>, ungrouped: list<array<string, mixed>>}
     */
    public function listVisibleGrouped(): array
    {
        $this->ensureDefaults();

        $tiles = AppMenuTile::query()
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $groups = AppMenuGroup::query()
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $byGroup = $tiles->groupBy(static fn (AppMenuTile $tile) => $tile->group_id ?? '');

        $grouped = [];
        foreach ($groups as $group) {
            $groupTiles = ($byGroup->get($group->id) ?? collect())
                ->map(static fn (AppMenuTile $tile) => $tile->toPublicArray())
                ->values()
                ->all();

            if ($groupTiles === []) {
                continue;
            }

            $grouped[] = $group->toPublicArray($groupTiles);
        }

        $ungrouped = ($byGroup->get('') ?? collect())
            ->map(static fn (AppMenuTile $tile) => $tile->toPublicArray())
            ->values()
            ->all();

        return [
            'settings' => $this->settings(),
            'groups' => $grouped,
            'ungrouped' => $ungrouped,
        ];
    }

    /**
     * @return array{settings: array{grid_columns: int}, groups: list<array<string, mixed>>, tiles: list<array<string, mixed>>}
     */
    public function listAllAdmin(): array
    {
        $this->ensureDefaults();

        return [
            'settings' => $this->settings(),
            'groups' => $this->listGroups(),
            'tiles' => AppMenuTile::query()
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->map(static fn (AppMenuTile $tile) => $tile->toApiArray())
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{grid_columns: int}
     */
    public function settings(): array
    {
        return $this->ensureSettings()->toApiArray();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{grid_columns: int}
     */
    public function updateSettings(array $input): array
    {
        $settings = $this->ensureSettings();
        if (array_key_exists('grid_columns', $input)) {
            $settings->grid_columns = max(3, min(6, (int) $input['grid_columns']));
        }
        $settings->save();

        return $settings->toApiArray();
    }

    /**
     * Assign tiles to a group (or ungrouped) and set sort order within that container.
     *
     * @param  list<string>  $orderedIds
     */
    public function placeInGroup(?string $groupId, array $orderedIds): void
    {
        if ($groupId !== null && $groupId !== '') {
            if (! AppMenuGroup::query()->where('id', $groupId)->exists()) {
                throw ValidationException::withMessages([
                    'group_id' => [__('Selected group was not found.')],
                ]);
            }
        } else {
            $groupId = null;
        }

        DB::transaction(function () use ($groupId, $orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                AppMenuTile::query()
                    ->where('id', $id)
                    ->update([
                        'group_id' => $groupId,
                        'sort_order' => $index,
                    ]);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGroups(): array
    {
        $this->ensureDefaults();

        return AppMenuGroup::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(static fn (AppMenuGroup $group) => $group->toApiArray())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createGroup(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => [__('Title is required.')],
            ]);
        }

        $key = array_key_exists('key', $input) ? trim((string) $input['key']) : '';
        $sortOrder = array_key_exists('sort_order', $input)
            ? max(0, (int) $input['sort_order'])
            : $this->nextGroupSortOrder();

        $group = AppMenuGroup::query()->create([
            'id' => (string) Str::uuid(),
            'key' => $key !== '' ? $key : null,
            'title' => $title,
            'sort_order' => $sortOrder,
            'is_visible' => array_key_exists('is_visible', $input) ? (bool) $input['is_visible'] : true,
        ]);

        return $group->toApiArray();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function updateGroup(AppMenuGroup $group, array $input): array
    {
        if (array_key_exists('title', $input)) {
            $title = trim((string) $input['title']);
            if ($title === '') {
                throw ValidationException::withMessages([
                    'title' => [__('Title is required.')],
                ]);
            }
            $group->title = $title;
        }

        if (array_key_exists('sort_order', $input)) {
            $group->sort_order = max(0, (int) $input['sort_order']);
        }

        if (array_key_exists('is_visible', $input)) {
            $group->is_visible = (bool) $input['is_visible'];
        }

        $group->save();

        return ($group->fresh() ?? $group)->toApiArray();
    }

    public function destroyGroup(AppMenuGroup $group): void
    {
        DB::transaction(function () use ($group): void {
            AppMenuTile::query()
                ->where('group_id', $group->id)
                ->update(['group_id' => null]);

            $group->delete();
        });
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function reorderGroups(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                AppMenuGroup::query()
                    ->where('id', $id)
                    ->update(['sort_order' => $index]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $payload = $this->normalizePayload($input, null);
        $payload['id'] = (string) Str::uuid();
        $payload['is_system'] = false;
        $payload['sort_order'] = $payload['sort_order'] ?? $this->nextSortOrder($payload['group_id'] ?? null);

        $tile = AppMenuTile::query()->create($payload);

        return $tile->toApiArray();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(AppMenuTile $tile, array $input): array
    {
        $payload = $this->normalizePayload($input, $tile);
        $tile->fill($payload);
        $tile->save();

        return ($tile->fresh() ?? $tile)->toApiArray();
    }

    public function destroy(AppMenuTile $tile): void
    {
        if ($tile->is_system) {
            throw ValidationException::withMessages([
                'tile' => [__('System tiles cannot be deleted. Hide them instead.')],
            ]);
        }

        app(AppMenuIconAssetService::class)->deleteForTile($tile);
        $tile->delete();
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                AppMenuTile::query()
                    ->where('id', $id)
                    ->update(['sort_order' => $index]);
            }
        });
    }

    /**
     * @return array{synced: int, message: string}
     */
    public function syncDefaults(): array
    {
        $synced = 0;
        $workspacesId = $this->ensureWorkspacesGroup();

        foreach (AppMenuTileDefaults::tiles() as $node) {
            $existing = AppMenuTile::query()->where('key', $node['key'])->first();
            if ($existing) {
                $existing->fill([
                    'title' => $node['title'],
                    'subtitle' => $node['subtitle'],
                    'icon' => $node['icon'],
                    'accent' => $node['accent'],
                    'is_system' => true,
                ]);
                if ($existing->group_id === null) {
                    $existing->group_id = $workspacesId;
                }
                $existing->save();
                $synced += 1;

                continue;
            }

            AppMenuTile::query()->create([
                ...$node,
                'id' => (string) Str::uuid(),
                'group_id' => $workspacesId,
            ]);
            $synced += 1;
        }

        AppMenuTile::query()
            ->whereNull('group_id')
            ->update(['group_id' => $workspacesId]);

        return [
            'synced' => $synced,
            'message' => __('Synced :count default App Menu tiles.', ['count' => $synced]),
        ];
    }

    private function ensureDefaults(): void
    {
        $this->ensureSettings();
        $this->ensureWorkspacesGroup();

        if (! AppMenuTile::query()->exists()) {
            $this->syncDefaults();
        }
    }

    private function ensureSettings(): AppMenuSetting
    {
        $existing = AppMenuSetting::query()->first();
        if ($existing) {
            return $existing;
        }

        $defaultColumns = (int) config('toweros.app_menu.grid_columns', 4);

        return AppMenuSetting::query()->create([
            'id' => (string) Str::uuid(),
            'grid_columns' => max(3, min(6, $defaultColumns > 0 ? $defaultColumns : 4)),
        ]);
    }

    private function ensureWorkspacesGroup(): string
    {
        $existing = AppMenuGroup::query()->where('key', 'workspaces')->first();
        if ($existing) {
            return (string) $existing->id;
        }

        $defaults = AppMenuGroupDefaults::groups()[0];
        $group = AppMenuGroup::query()->create([
            'id' => (string) Str::uuid(),
            ...$defaults,
        ]);

        return (string) $group->id;
    }

    private function nextSortOrder(?string $groupId): int
    {
        $query = AppMenuTile::query();
        if ($groupId === null) {
            $query->whereNull('group_id');
        } else {
            $query->where('group_id', $groupId);
        }

        return ((int) $query->max('sort_order')) + 1;
    }

    private function nextGroupSortOrder(): int
    {
        return ((int) AppMenuGroup::query()->max('sort_order')) + 1;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizePayload(array $input, ?AppMenuTile $existing): array
    {
        $title = array_key_exists('title', $input)
            ? trim((string) $input['title'])
            : (string) ($existing?->title ?? '');
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => [__('Title is required.')],
            ]);
        }

        $href = array_key_exists('href', $input)
            ? trim((string) $input['href'])
            : (string) ($existing?->href ?? '');
        if ($href === '') {
            throw ValidationException::withMessages([
                'href' => [__('Href is required.')],
            ]);
        }

        $payload = [
            'title' => $title,
            'href' => $href,
        ];

        if (array_key_exists('subtitle', $input)) {
            $subtitle = trim((string) ($input['subtitle'] ?? ''));
            $payload['subtitle'] = $subtitle !== '' ? $subtitle : null;
        } elseif ($existing === null) {
            $payload['subtitle'] = null;
        }

        if (array_key_exists('icon', $input)) {
            $icon = trim((string) ($input['icon'] ?? ''));
            $payload['icon'] = $icon !== '' ? $icon : null;
        } elseif ($existing === null) {
            $payload['icon'] = 'Shapes';
        }

        if (array_key_exists('accent', $input)) {
            $accent = trim((string) ($input['accent'] ?? ''));
            $payload['accent'] = $accent !== '' ? $accent : null;
        } elseif ($existing === null) {
            $payload['accent'] = 'sky';
        }

        if (array_key_exists('group_id', $input)) {
            $groupId = $input['group_id'];
            if ($groupId === null || $groupId === '') {
                $payload['group_id'] = null;
            } else {
                $groupId = trim((string) $groupId);
                if (! AppMenuGroup::query()->where('id', $groupId)->exists()) {
                    throw ValidationException::withMessages([
                        'group_id' => [__('Selected group was not found.')],
                    ]);
                }
                $payload['group_id'] = $groupId;
            }
        } elseif ($existing === null) {
            $payload['group_id'] = $this->ensureWorkspacesGroup();
        }

        if (array_key_exists('open_in_new_tab', $input)) {
            $payload['open_in_new_tab'] = (bool) $input['open_in_new_tab'];
        } elseif ($existing === null) {
            $payload['open_in_new_tab'] = $this->defaultOpenInNewTab($href);
        }

        if (array_key_exists('sort_order', $input)) {
            $payload['sort_order'] = max(0, (int) $input['sort_order']);
        }

        if (array_key_exists('is_visible', $input)) {
            $payload['is_visible'] = (bool) $input['is_visible'];
        } elseif ($existing === null) {
            $payload['is_visible'] = true;
        }

        if (array_key_exists('key', $input) && $existing === null) {
            $key = trim((string) $input['key']);
            $payload['key'] = $key !== '' ? $key : null;
        }

        return $payload;
    }

    private function defaultOpenInNewTab(string $href): bool
    {
        if (str_starts_with($href, '/')) {
            return false;
        }

        $host = parse_url($href, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return true;
        }

        return ! str_contains(strtolower($host), 'myapp.localhost')
            && ! str_contains(strtolower($host), 'localhost');
    }
}
