<?php

declare(strict_types=1);

namespace App\Modules\Help\Services;

use App\Modules\Help\Models\HelpGuide;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class HelpGuideService
{
    /**
     * @return Collection<int, HelpGuide>
     */
    public function listForAdmin(?string $moduleKey = null): Collection
    {
        if (! $this->guidesTableExists()) {
            return collect();
        }

        return HelpGuide::query()
            ->when($moduleKey !== null && $moduleKey !== '', fn ($q) => $q->where('module_key', $moduleKey))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    /**
     * @return Collection<int, HelpGuide>
     */
    public function listPublished(?string $moduleKey = null, ?string $role = null): Collection
    {
        if (! $this->guidesTableExists()) {
            return collect();
        }

        return HelpGuide::query()
            ->where('status', HelpGuide::STATUS_PUBLISHED)
            ->when($moduleKey !== null && $moduleKey !== '', fn ($q) => $q->where('module_key', $moduleKey))
            ->when(
                $role !== null && $role !== '' && $role !== HelpGuide::ROLE_ALL,
                fn ($q) => $q->where(function ($inner) use ($role): void {
                    $inner->where('role', $role)->orWhere('role', HelpGuide::ROLE_ALL);
                }),
            )
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function findBySlugOrFail(string $slug): HelpGuide
    {
        if (! $this->guidesTableExists()) {
            abort(404);
        }

        $guide = HelpGuide::query()->where('slug', $slug)->first();
        if (! $guide instanceof HelpGuide) {
            abort(404);
        }

        return $guide;
    }

    public function findPublishedBySlugOrFail(string $slug): HelpGuide
    {
        if (! $this->guidesTableExists()) {
            abort(404);
        }

        $guide = HelpGuide::query()
            ->where('slug', $slug)
            ->where('status', HelpGuide::STATUS_PUBLISHED)
            ->first();

        if (! $guide instanceof HelpGuide) {
            abort(404);
        }

        return $guide;
    }

    /**
     * @param  array{
     *   title?: string,
     *   body?: string,
     *   role?: string,
     *   sort_order?: int,
     *   module_key?: string
     * }  $payload
     */
    public function update(TenantUser $actor, HelpGuide $guide, array $payload): HelpGuide
    {
        if (isset($payload['title'])) {
            $title = trim((string) $payload['title']);
            if ($title === '') {
                throw ValidationException::withMessages([
                    'title' => [__('Title is required.')],
                ]);
            }
            $guide->title = $title;
        }

        if (isset($payload['body'])) {
            $body = trim((string) $payload['body']);
            if ($body === '') {
                throw ValidationException::withMessages([
                    'body' => [__('Guide body is required.')],
                ]);
            }
            $guide->body = $body;
            $guide->content_checksum = hash('sha256', $body);
        }

        if (isset($payload['role'])) {
            $role = strtolower(trim((string) $payload['role']));
            if (! in_array($role, [
                HelpGuide::ROLE_REQUESTOR,
                HelpGuide::ROLE_APPROVER,
                HelpGuide::ROLE_ALL,
            ], true)) {
                throw ValidationException::withMessages([
                    'role' => [__('Invalid guide role.')],
                ]);
            }
            $guide->role = $role;
        }

        if (array_key_exists('sort_order', $payload)) {
            $guide->sort_order = max(0, (int) $payload['sort_order']);
        }

        // Editing a published guide keeps it published (admins expect live updates).
        $guide->updated_by = $actor->id;
        $guide->save();

        return $guide->fresh() ?? $guide;
    }

    public function publish(TenantUser $actor, HelpGuide $guide): HelpGuide
    {
        if (trim($guide->body) === '') {
            throw new RuntimeException(__('Cannot publish an empty guide.'));
        }

        $guide->status = HelpGuide::STATUS_PUBLISHED;
        $guide->updated_by = $actor->id;
        $guide->save();

        return $guide->fresh() ?? $guide;
    }

    public function unpublish(TenantUser $actor, HelpGuide $guide): HelpGuide
    {
        $guide->status = HelpGuide::STATUS_DRAFT;
        $guide->updated_by = $actor->id;
        $guide->save();

        return $guide->fresh() ?? $guide;
    }

    /**
     * @return array<string, mixed>
     */
    public function asListRow(HelpGuide $guide): array
    {
        return [
            'id' => (string) $guide->id,
            'module_key' => $guide->module_key,
            'slug' => $guide->slug,
            'role' => $guide->role,
            'title' => $guide->title,
            'status' => $guide->status,
            'sort_order' => $guide->sort_order,
            'updated_at' => $guide->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asDetail(HelpGuide $guide): array
    {
        return [
            ...$this->asListRow($guide),
            'body' => $guide->body,
            'content_checksum' => $guide->content_checksum,
            'created_at' => $guide->created_at?->toIso8601String(),
        ];
    }

    /**
     * Seed or refresh a guide. Never overwrites body when checksum no longer matches the seed template.
     *
     * @return array{action: 'created'|'updated'|'skipped', guide: HelpGuide}
     */
    public function seedGuide(
        string $moduleKey,
        string $slug,
        string $role,
        string $title,
        string $body,
        int $sortOrder = 0,
        bool $force = false,
    ): array {
        if (! $this->guidesTableExists()) {
            throw new RuntimeException('help_guides table is missing. Run php artisan toweros:migrate first.');
        }

        $body = trim($body);
        $checksum = hash('sha256', $body);

        $existing = HelpGuide::query()
            ->where('module_key', $moduleKey)
            ->where('slug', $slug)
            ->first();

        if ($existing instanceof HelpGuide) {
            $unchangedFromSeed = $existing->content_checksum === $checksum;
            if (! $force && ! $unchangedFromSeed) {
                return ['action' => 'skipped', 'guide' => $existing];
            }

            $existing->title = $title;
            $existing->role = $role;
            $existing->body = $body;
            $existing->sort_order = $sortOrder;
            $existing->content_checksum = $checksum;
            $existing->status = HelpGuide::STATUS_PUBLISHED;
            $existing->save();

            return ['action' => 'updated', 'guide' => $existing];
        }

        $guide = HelpGuide::query()->create([
            'id' => (string) Str::uuid(),
            'module_key' => $moduleKey,
            'slug' => $slug,
            'role' => $role,
            'title' => $title,
            'body' => $body,
            'status' => HelpGuide::STATUS_PUBLISHED,
            'sort_order' => $sortOrder,
            'content_checksum' => $checksum,
            'created_by' => null,
            'updated_by' => null,
        ]);

        return ['action' => 'created', 'guide' => $guide];
    }

    private function guidesTableExists(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        try {
            $exists = Schema::connection('tenant')->hasTable('help_guides');
        } catch (Throwable) {
            $exists = false;
        }

        return $exists;
    }
}
