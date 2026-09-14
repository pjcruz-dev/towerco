<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Core\Services\AbstractDomainService;
use App\Modules\Identity\Models\ModuleListSharedLayout;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\UserUiPreference;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserUiPreferenceService extends AbstractDomainService
{
    /** module-list.* (named column layouts) or dashboard-layout.* (page board prefs). */
    public const KEY_PATTERN = '/^(module-list|dashboard-layout)\.[A-Za-z0-9._-]{1,140}$/';

    public const MAX_VALUE_BYTES = 65536;

    /**
     * @return array{value: array<string, mixed>|null, shared: array<string, mixed>|null}
     */
    public function getWithShared(TenantUser $user, string $key): array
    {
        return [
            'value' => $this->get($user, $key),
            'shared' => $this->getShared($key),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(TenantUser $user, string $key): ?array
    {
        $key = $this->assertKey($key);
        $row = UserUiPreference::query()
            ->where('user_id', $user->id)
            ->where('preference_key', $key)
            ->first();

        if ($row === null) {
            return null;
        }

        return is_array($row->value_json) ? $row->value_json : null;
    }

    /**
     * Tenant-wide shared payload for a preference key.
     *
     * @return array<string, mixed>|null
     */
    public function getShared(string $key): ?array
    {
        $key = $this->assertKey($key);
        $row = ModuleListSharedLayout::query()
            ->where('storage_key', $key)
            ->first();

        if ($row === null) {
            return null;
        }

        $payload = is_array($row->layouts_json) ? $row->layouts_json : [];
        $meta = [
            'updated_by' => $row->updated_by,
            'updated_at' => optional($row->updated_at)?->toIso8601String(),
        ];

        if ($this->isDashboardLayoutKey($key)) {
            $layout = $payload['layout'] ?? null;

            return [
                'layout' => is_array($layout) ? $layout : null,
                ...$meta,
            ];
        }

        $layouts = $payload['layouts'] ?? $payload;

        return [
            'layouts' => is_array($layouts) ? $layouts : [],
            ...$meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    public function put(TenantUser $user, string $key, array $value): array
    {
        $key = $this->assertKey($key);
        $this->assertValueSize($value);

        $row = UserUiPreference::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'preference_key' => $key,
            ],
            [
                'value_json' => $value,
            ],
        );

        return is_array($row->value_json) ? $row->value_json : [];
    }

    public function clear(TenantUser $user, string $key): void
    {
        $key = $this->assertKey($key);
        UserUiPreference::query()
            ->where('user_id', $user->id)
            ->where('preference_key', $key)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    public function putShared(TenantUser $user, string $key, array $value): array
    {
        abort_unless(
            $user->can('tenant:manage') || $user->can('user:manage'),
            403,
            'Sharing layouts with the tenant requires an admin role.',
        );

        $key = $this->assertKey($key);
        $this->assertValueSize($value);

        if ($this->isDashboardLayoutKey($key)) {
            $layout = $value['layout'] ?? null;
            if (! is_array($layout)) {
                throw ValidationException::withMessages([
                    'value.layout' => 'Shared dashboard layout payload must include a layout object.',
                ]);
            }
            $normalized = ['layout' => $layout];
        } else {
            $layouts = $value['layouts'] ?? null;
            if (! is_array($layouts)) {
                throw ValidationException::withMessages([
                    'value.layouts' => 'Shared layouts payload must include a layouts array.',
                ]);
            }
            $normalized = ['layouts' => array_values(array_slice($layouts, 0, 12))];
        }

        $existing = ModuleListSharedLayout::query()->where('storage_key', $key)->first();
        if ($existing === null) {
            ModuleListSharedLayout::query()->create([
                'id' => (string) Str::uuid(),
                'storage_key' => $key,
                'layouts_json' => $normalized,
                'updated_by' => $user->id,
            ]);
        } else {
            $existing->forceFill([
                'layouts_json' => $normalized,
                'updated_by' => $user->id,
            ])->save();
        }

        return $this->getShared($key) ?? ($this->isDashboardLayoutKey($key)
            ? ['layout' => null]
            : ['layouts' => []]);
    }

    public function clearShared(string $key, ?TenantUser $actor = null): void
    {
        if ($actor !== null) {
            abort_unless(
                $actor->can('tenant:manage') || $actor->can('user:manage'),
                403,
                'Clearing shared layouts requires an admin role.',
            );
        }

        $key = $this->assertKey($key);
        ModuleListSharedLayout::query()->where('storage_key', $key)->delete();
    }

    /**
     * Tenant-wide shared layout audit rows (module-list + dashboard-layout).
     *
     * @return list<array{
     *   key: string,
     *   kind: 'dashboard-layout'|'module-list',
     *   summary: string,
     *   updated_by: string|null,
     *   updated_by_name: string|null,
     *   updated_at: string|null
     * }>
     */
    public function listShared(TenantUser $actor): array
    {
        abort_unless(
            $actor->can('tenant:manage') || $actor->can('user:manage'),
            403,
            'Listing shared layouts requires an admin role.',
        );

        $rows = ModuleListSharedLayout::query()
            ->with(['updater:id,name,email'])
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $key = (string) $row->storage_key;
            $payload = is_array($row->layouts_json) ? $row->layouts_json : [];
            $kind = $this->isDashboardLayoutKey($key) ? 'dashboard-layout' : 'module-list';
            $summary = $kind === 'dashboard-layout'
                ? (isset($payload['layout']) && is_array($payload['layout'])
                    ? 'Tenant default page board'
                    : 'Empty dashboard layout')
                : sprintf(
                    '%d named column layout%s',
                    is_array($payload['layouts'] ?? null) ? count($payload['layouts']) : 0,
                    (is_array($payload['layouts'] ?? null) && count($payload['layouts']) === 1) ? '' : 's',
                );

            $updater = $row->updater;
            $out[] = [
                'key' => $key,
                'kind' => $kind,
                'summary' => $summary,
                'updated_by' => $row->updated_by,
                'updated_by_name' => $updater?->name ?? $updater?->email,
                'updated_at' => optional($row->updated_at)?->toIso8601String(),
            ];
        }

        return $out;
    }

    public function isDashboardLayoutKey(string $key): bool
    {
        return str_starts_with($key, 'dashboard-layout.');
    }

    private function assertKey(string $key): string
    {
        $trimmed = trim($key);
        if ($trimmed === '' || ! preg_match(self::KEY_PATTERN, $trimmed)) {
            throw ValidationException::withMessages([
                'key' => 'Preference key must match module-list.* or dashboard-layout.* and use safe characters.',
            ]);
        }

        return $trimmed;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function assertValueSize(array $value): void
    {
        $encoded = json_encode($value);
        if ($encoded === false || strlen($encoded) > self::MAX_VALUE_BYTES) {
            throw ValidationException::withMessages([
                'value' => 'Preference value is too large.',
            ]);
        }
    }
}
