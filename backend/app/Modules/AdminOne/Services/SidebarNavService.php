<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\AdminOne\Models\SidebarNavItem;
use App\Modules\AdminOne\Models\SidebarNavRoleDefault;
use App\Modules\AdminOne\Support\SidebarNavSeedCatalog;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class SidebarNavService
{
    public const TYPES = ['header', 'entity_link', 'internal_page', 'external_link', 'divider'];

    /**
     * @return array{items: list<array<string, mixed>>, roles: list<array{id: int, name: string}>, seeded: bool}
     */
    public function adminTree(): array
    {
        $this->ensureSeeded();

        $items = SidebarNavItem::query()->orderBy('sort_order')->orderBy('title')->get();
        $defaults = SidebarNavRoleDefault::query()->get()->groupBy('item_id');

        $presented = $items->map(function (SidebarNavItem $item) use ($defaults) {
            $roleIds = ($defaults->get($item->id) ?? collect())->pluck('role_id')->map(static fn ($id) => (int) $id)->values()->all();

            return $this->presentItem($item, $roleIds);
        })->values()->all();

        $roles = Role::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Role $role): array => [
                'id' => (int) $role->id,
                'name' => (string) $role->name,
            ])
            ->values()
            ->all();

        return [
            'items' => $presented,
            'roles' => $roles,
            'seeded' => true,
        ];
    }

    public function ensureSeeded(bool $force = false): void
    {
        if (! $force && SidebarNavItem::query()->exists()) {
            $this->syncMissingSeedNodes(SidebarNavSeedCatalog::tree(), null);

            return;
        }

        if ($force) {
            SidebarNavRoleDefault::query()->delete();
            // Self-FK: remove leaves until empty.
            $guard = 0;
            while ($guard < 64 && SidebarNavItem::query()->exists()) {
                $guard += 1;
                $leafIds = SidebarNavItem::query()
                    ->whereDoesntHave('children')
                    ->pluck('id');
                if ($leafIds->isEmpty()) {
                    SidebarNavItem::query()->delete();
                    break;
                }
                SidebarNavItem::query()->whereIn('id', $leafIds)->delete();
            }
        }

        foreach (SidebarNavSeedCatalog::tree() as $node) {
            $this->insertSeedNode($node, null);
        }
    }

    /**
     * Insert seed nodes whose key is not already present (additive — does not overwrite customizations).
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    private function syncMissingSeedNodes(array $nodes, ?string $parentId): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $key = isset($node['key']) ? trim((string) $node['key']) : '';
            if ($key === '') {
                continue;
            }

            $existing = SidebarNavItem::query()->where('key', $key)->first();
            if ($existing) {
                if ($existing->is_system) {
                    $this->syncSystemSeedMetadata($existing, $node);
                }
                $children = $node['children'] ?? [];
                if (is_array($children) && $children !== []) {
                    $this->syncMissingSeedNodes($children, (string) $existing->id);
                }

                continue;
            }

            // insertSeedNode creates the node and its full descendant tree from the seed catalog.
            $this->insertSeedNode($node, $parentId);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SidebarNavItem
    {
        $payload = $this->normalizePayload($data, null);
        $payload['id'] = (string) Str::uuid();
        $payload['is_system'] = false;
        $payload['sort_order'] = $payload['sort_order'] ?? $this->nextSortOrder($payload['parent_id'] ?? null);

        if (! empty($payload['is_global_default'])) {
            SidebarNavItem::query()->where('is_global_default', true)->update(['is_global_default' => false]);
        }

        $item = SidebarNavItem::query()->create($payload);
        $this->syncRoleDefaults($item, $data['role_default_ids'] ?? []);

        return $item->fresh() ?? $item;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SidebarNavItem $item, array $data): SidebarNavItem
    {
        $payload = $this->normalizePayload($data, $item);

        if (($payload['parent_id'] ?? null) === $item->id) {
            throw ValidationException::withMessages([
                'parent_id' => [__('An item cannot be its own parent.')],
            ]);
        }

        if (! empty($payload['is_global_default'])) {
            SidebarNavItem::query()
                ->where('is_global_default', true)
                ->where('id', '!=', $item->id)
                ->update(['is_global_default' => false]);
        }

        $item->fill($payload);
        $item->save();

        if (array_key_exists('role_default_ids', $data)) {
            $this->syncRoleDefaults($item, $data['role_default_ids'] ?? []);
        }

        return $item->fresh() ?? $item;
    }

    public function destroy(SidebarNavItem $item): void
    {
        SidebarNavRoleDefault::query()->where('item_id', $item->id)->delete();
        $item->delete();
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function reorder(array $orderedIds, ?string $parentId = null): void
    {
        DB::connection('tenant')->transaction(function () use ($orderedIds, $parentId): void {
            foreach (array_values($orderedIds) as $index => $id) {
                SidebarNavItem::query()
                    ->where('id', $id)
                    ->update([
                        'parent_id' => $parentId,
                        'sort_order' => $index,
                    ]);
            }
        });
    }

    /**
     * @return array{groups: list<array<string, mixed>>, default_landing_href: string}
     */
    public function resolveForUser(TenantUser $user): array
    {
        $this->ensureSeeded();

        $enabledModules = app(TenantEnabledModulesResolver::class)->resolveForCurrentTenant();
        $permissions = $user->getAllPermissions()->pluck('name')->all();
        $roleIds = $user->roles()->pluck('id')->map(static fn ($id) => (int) $id)->all();

        $items = SidebarNavItem::query()
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        $byParent = $items->groupBy(static fn (SidebarNavItem $item) => $item->parent_id ?? 'root');

        $build = function (?string $parentId) use (&$build, $byParent, $enabledModules, $permissions): array {
            $key = $parentId ?? 'root';
            $rows = $byParent->get($key) ?? collect();
            $out = [];
            foreach ($rows as $item) {
                /** @var SidebarNavItem $item */
                if (! $this->passesModule($item, $enabledModules)) {
                    continue;
                }
                if (! $this->passesPermissions($item, $permissions)) {
                    continue;
                }

                $children = $build($item->id);
                if ($item->type === 'header' && $children === [] && $item->href === null && $item->entity_slug === null) {
                    // Keep empty headers only when they are top-level group labels with no link.
                    // Nested headers without visible children are omitted.
                    if ($parentId !== null) {
                        continue;
                    }
                }

                $href = $this->resolveHref($item);
                $out[] = [
                    'id' => $item->id,
                    'key' => $item->key,
                    'type' => $item->type,
                    'title' => $item->title,
                    'icon' => $item->icon,
                    'href' => $href,
                    'module' => $item->module,
                    'exact' => $this->shouldMatchExact($item, $href),
                    'children' => $children,
                ];
            }

            return $out;
        };

        $roots = $build(null);
        // Present as groups: root headers become groups; lone links go under Operations fallback.
        $groups = [];
        $loose = [];
        foreach ($roots as $root) {
            if ($root['type'] === 'header') {
                $groups[] = [
                    'group' => $root['title'],
                    'items' => $this->toRuntimeItems($root['children']),
                ];
            } else {
                $loose[] = $root;
            }
        }
        if ($loose !== []) {
            array_unshift($groups, [
                'group' => 'Workspace',
                'items' => $this->toRuntimeItems($loose),
            ]);
        }

        return [
            'groups' => array_values(array_filter($groups, static fn (array $g): bool => ($g['items'] ?? []) !== [])),
            'default_landing_href' => $this->resolveDefaultLandingHref($roleIds, $items),
        ];
    }

    /**
     * @param  list<int>  $roleIds
     * @param  \Illuminate\Support\Collection<int, SidebarNavItem>  $items
     */
    private function resolveDefaultLandingHref(array $roleIds, $items): string
    {
        foreach ($roleIds as $roleId) {
            $default = SidebarNavRoleDefault::query()->where('role_id', $roleId)->first();
            if ($default) {
                $item = $items->firstWhere('id', $default->item_id);
                if ($item instanceof SidebarNavItem) {
                    $href = $this->resolveHref($item);
                    if (is_string($href) && $href !== '') {
                        return $href;
                    }
                }
            }
        }

        $global = $items->first(static fn (SidebarNavItem $item): bool => (bool) $item->is_global_default);
        if ($global instanceof SidebarNavItem) {
            $href = $this->resolveHref($global);
            if (is_string($href) && $href !== '') {
                return $href;
            }
        }

        return '/dashboard';
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function toRuntimeItems(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'divider') {
                continue;
            }
            $children = $this->toRuntimeItems($node['children'] ?? []);
            $href = $node['href'] ?? null;
            $type = (string) ($node['type'] ?? '');
            // Headers/folders may have null href; leaf links without a resolvable href are dead.
            if ($children === [] && ($href === null || $href === '') && $type !== 'header') {
                continue;
            }
            $item = [
                'title' => $node['title'],
                'icon' => $node['icon'],
                'href' => $href,
                'module' => $node['module'],
                'exact' => (bool) ($node['exact'] ?? false),
            ];
            if ($children !== []) {
                $item['items'] = $children;
            }
            $out[] = $item;
        }

        return $out;
    }

    private function shouldMatchExact(SidebarNavItem $item, ?string $href): bool
    {
        $key = (string) ($item->key ?? '');
        if (str_ends_with($key, '.all-entities') || str_ends_with($key, '.overview')) {
            return true;
        }

        $exactIndexes = [
            '/dashboard',
            '/dynamic-entities',
            '/ticketing',
            '/e-approval',
            '/settings',
            '/help',
            '/dynamic-entities/executive-dashboard',
            '/dynamic-entities/ticketing-board',
        ];

        return is_string($href) && in_array($href, $exactIndexes, true);
    }

    /**
     * Refresh catalog metadata for seeded system nodes (title, href, sort order, permissions).
     *
     * @param  array<string, mixed>  $node
     */
    private function syncSystemSeedMetadata(SidebarNavItem $existing, array $node): void
    {
        $perms = array_values(array_filter(array_map('strval', $node['required_permissions'] ?? [])));
        $dirty = false;

        $title = (string) ($node['title'] ?? $existing->title);
        if ($title !== '' && $existing->title !== $title) {
            $existing->title = $title;
            $dirty = true;
        }

        $href = array_key_exists('href', $node) ? $node['href'] : $existing->href;
        if ($existing->href !== $href) {
            $existing->href = is_string($href) ? $href : null;
            $dirty = true;
        }

        $icon = array_key_exists('icon', $node) ? $node['icon'] : $existing->icon;
        if ($existing->icon !== $icon) {
            $existing->icon = is_string($icon) && $icon !== '' ? $icon : null;
            $dirty = true;
        }

        $sortOrder = (int) ($node['sort_order'] ?? $existing->sort_order);
        if ((int) $existing->sort_order !== $sortOrder) {
            $existing->sort_order = $sortOrder;
            $dirty = true;
        }

        $entitySlug = array_key_exists('entity_slug', $node) ? $node['entity_slug'] : $existing->entity_slug;
        if ($existing->entity_slug !== $entitySlug) {
            $existing->entity_slug = is_string($entitySlug) && $entitySlug !== '' ? $entitySlug : null;
            $dirty = true;
        }

        $match = ($node['permissions_match'] ?? 'all') === 'any' ? 'any' : 'all';
        if ($existing->permissions_match !== $match) {
            $existing->permissions_match = $match;
            $dirty = true;
        }

        if ($existing->required_permissions !== $perms) {
            $existing->required_permissions = $perms;
            $existing->permission_key = $perms[0] ?? null;
            $dirty = true;
        }

        $module = array_key_exists('module', $node) ? $node['module'] : $existing->module;
        if ($existing->module !== $module) {
            $existing->module = is_string($module) && $module !== '' ? $module : null;
            $dirty = true;
        }

        if ($dirty) {
            $existing->save();
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function insertSeedNode(array $node, ?string $parentId): void
    {
        $perms = array_values(array_filter(array_map('strval', $node['required_permissions'] ?? [])));
        $item = SidebarNavItem::query()->create([
            'id' => (string) Str::uuid(),
            'parent_id' => $parentId,
            'key' => $node['key'] ?? null,
            'type' => $node['type'] ?? 'internal_page',
            'title' => (string) ($node['title'] ?? 'Item'),
            'icon' => $node['icon'] ?? null,
            'href' => $node['href'] ?? null,
            'entity_slug' => $node['entity_slug'] ?? null,
            'permission_key' => $perms[0] ?? null,
            'required_permissions' => $perms,
            'permissions_match' => ($node['permissions_match'] ?? 'all') === 'any' ? 'any' : 'all',
            'module' => $node['module'] ?? null,
            'sort_order' => (int) ($node['sort_order'] ?? 0),
            'is_system' => true,
            'is_visible' => true,
            'is_global_default' => ($node['key'] ?? '') === 'group.operations.dashboard',
        ]);

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $this->insertSeedNode($child, $item->id);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, ?SidebarNavItem $existing): array
    {
        $type = (string) ($data['type'] ?? $existing?->type ?? 'internal_page');
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => [__('Invalid sidebar item type.')],
            ]);
        }

        $title = trim((string) ($data['title'] ?? $existing?->title ?? ''));
        if ($title === '' && $type !== 'divider') {
            throw ValidationException::withMessages([
                'title' => [__('Label is required.')],
            ]);
        }

        $permissionKey = array_key_exists('permission_key', $data)
            ? (trim((string) $data['permission_key']) ?: null)
            : $existing?->permission_key;

        $required = $existing?->required_permissions ?? [];
        if (array_key_exists('permission_key', $data)) {
            $required = $permissionKey ? [$permissionKey] : [];
        }
        if (array_key_exists('required_permissions', $data) && is_array($data['required_permissions'])) {
            $required = array_values(array_filter(array_map('strval', $data['required_permissions'])));
            $permissionKey = $required[0] ?? null;
        }

        $href = array_key_exists('href', $data) ? (trim((string) $data['href']) ?: null) : $existing?->href;
        $entitySlug = array_key_exists('entity_slug', $data)
            ? (trim((string) $data['entity_slug']) ?: null)
            : $existing?->entity_slug;

        if ($type === 'entity_link' && $entitySlug) {
            $href = null;
        }

        if ($type === 'entity_link' && ! $entitySlug) {
            throw ValidationException::withMessages([
                'entity_slug' => [__('Entity slug is required for entity links.')],
            ]);
        }

        if (in_array($type, ['internal_page', 'external_link'], true) && ! $href) {
            throw ValidationException::withMessages([
                'href' => [__('Href is required for this item type.')],
            ]);
        }

        $payload = [
            'parent_id' => array_key_exists('parent_id', $data)
                ? ($data['parent_id'] ? (string) $data['parent_id'] : null)
                : $existing?->parent_id,
            'type' => $type,
            'title' => $title !== '' ? $title : ($type === 'divider' ? '—' : 'Item'),
            'icon' => array_key_exists('icon', $data) ? (trim((string) $data['icon']) ?: null) : $existing?->icon,
            'href' => $href,
            'entity_slug' => $entitySlug,
            'permission_key' => $permissionKey,
            'required_permissions' => $required,
            'permissions_match' => (($data['permissions_match'] ?? $existing?->permissions_match ?? 'all') === 'any') ? 'any' : 'all',
            'module' => array_key_exists('module', $data) ? (trim((string) $data['module']) ?: null) : $existing?->module,
            'is_visible' => array_key_exists('is_visible', $data)
                ? (bool) $data['is_visible']
                : ($existing?->is_visible ?? true),
            'is_global_default' => array_key_exists('is_global_default', $data)
                ? (bool) $data['is_global_default']
                : ($existing?->is_global_default ?? false),
        ];

        if (array_key_exists('sort_order', $data)) {
            $payload['sort_order'] = (int) $data['sort_order'];
        }

        if (array_key_exists('key', $data) && $existing === null) {
            $key = trim((string) $data['key']);
            $payload['key'] = $key !== '' ? $key : null;
        }

        return $payload;
    }

    /**
     * @param  list<int|string>  $roleIds
     */
    private function syncRoleDefaults(SidebarNavItem $item, array $roleIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $roleIds)));

        // Checking a role clears that role's previous default.
        if ($ids !== []) {
            SidebarNavRoleDefault::query()->whereIn('role_id', $ids)->delete();
        }

        SidebarNavRoleDefault::query()->where('item_id', $item->id)->delete();

        foreach ($ids as $roleId) {
            if ($roleId <= 0) {
                continue;
            }
            SidebarNavRoleDefault::query()->create([
                'role_id' => $roleId,
                'item_id' => $item->id,
            ]);
        }
    }

    private function nextSortOrder(?string $parentId): int
    {
        $max = SidebarNavItem::query()
            ->when(
                $parentId === null,
                static fn ($q) => $q->whereNull('parent_id'),
                static fn ($q) => $q->where('parent_id', $parentId),
            )
            ->max('sort_order');

        return ((int) $max) + 1;
    }

    /**
     * @param  list<int>  $roleDefaultIds
     * @return array<string, mixed>
     */
    private function presentItem(SidebarNavItem $item, array $roleDefaultIds): array
    {
        return [
            'id' => $item->id,
            'parent_id' => $item->parent_id,
            'key' => $item->key,
            'type' => $item->type,
            'title' => $item->title,
            'icon' => $item->icon,
            'href' => $item->href,
            'entity_slug' => $item->entity_slug,
            'permission_key' => $item->permission_key,
            'required_permissions' => $item->required_permissions ?? [],
            'permissions_match' => $item->permissions_match,
            'module' => $item->module,
            'sort_order' => $item->sort_order,
            'is_system' => $item->is_system,
            'is_visible' => $item->is_visible,
            'is_global_default' => $item->is_global_default,
            'role_default_ids' => $roleDefaultIds,
            'resolved_href' => $this->resolveHref($item),
        ];
    }

    private function resolveHref(SidebarNavItem $item): ?string
    {
        if ($item->type === 'divider' || $item->type === 'header') {
            return $item->href;
        }
        if ($item->type === 'entity_link' && $item->entity_slug) {
            return '/dynamic-entities/'.$item->entity_slug;
        }

        return $item->href;
    }

    /**
     * @param  list<string>  $enabledModules
     */
    private function passesModule(SidebarNavItem $item, array $enabledModules): bool
    {
        $module = trim((string) ($item->module ?? ''));
        if ($module === '' || $module === 'core') {
            return true;
        }

        return in_array($module, $enabledModules, true);
    }

    /**
     * @param  list<string>  $userPermissions
     */
    private function passesPermissions(SidebarNavItem $item, array $userPermissions): bool
    {
        $required = array_values(array_filter(array_map('strval', $item->required_permissions ?? [])));
        if ($required === [] && $item->permission_key) {
            $required = [(string) $item->permission_key];
        }
        if ($required === []) {
            return true;
        }

        if (($item->permissions_match ?? 'all') === 'any') {
            foreach ($required as $perm) {
                if (in_array($perm, $userPermissions, true)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($required as $perm) {
            if (! in_array($perm, $userPermissions, true)) {
                return false;
            }
        }

        return true;
    }
}
