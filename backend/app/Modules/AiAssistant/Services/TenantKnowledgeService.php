<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Jobs\IngestKnowledgeSourceJob;
use App\Modules\AiAssistant\Models\AiKnowledgeChunk;
use App\Modules\AiAssistant\Models\AiKnowledgeSource;
use App\Modules\AiAssistant\Support\AssistantKnowledgeScope;
use App\Modules\AiAssistant\Support\AssistantKnowledgeSourceType;
use App\Modules\AiAssistant\Support\AssistantKnowledgeStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class TenantKnowledgeService
{
    public function __construct(
        private readonly KnowledgeIngestionService $ingestion,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AiKnowledgeSource>
     */
    public function paginate(
        int $page = 1,
        int $perPage = 25,
        string $search = '',
        ?string $status = null,
    ): LengthAwarePaginator {
        $query = AiKnowledgeSource::query()
            ->where('scope', AssistantKnowledgeScope::TENANT)
            ->withCount('chunks')
            ->orderByDesc('updated_at');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function (Builder $inner) use ($like): void {
                $inner->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('module_key', 'like', $like);
            });
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }

    public function findTenantSourceOrFail(string $id): AiKnowledgeSource
    {
        $source = AiKnowledgeSource::query()
            ->where('scope', AssistantKnowledgeScope::TENANT)
            ->withCount('chunks')
            ->find($id);

        abort_if($source === null, 404, __('Knowledge source not found.'));

        return $source;
    }

    /**
     * @param  array{
     *   title: string,
     *   body: string,
     *   slug?: string|null,
     *   module_key?: string|null,
     *   audience?: string|null,
     *   required_permissions?: list<string>|null,
     *   related_routes?: list<string>|null
     * }  $payload
     */
    public function createDraft(TenantUser $actor, array $payload): AiKnowledgeSource
    {
        $slug = $this->resolveUniqueSlug($payload['slug'] ?? null, $payload['title']);

        $source = AiKnowledgeSource::query()->create([
            'slug' => $slug,
            'scope' => AssistantKnowledgeScope::TENANT,
            'module_key' => $this->nullableString($payload['module_key'] ?? null),
            'title' => trim($payload['title']),
            'source_type' => AssistantKnowledgeSourceType::MANUAL,
            'source_path' => null,
            'source_url' => null,
            'body' => trim($payload['body']),
            'audience' => $this->nullableString($payload['audience'] ?? null) ?? 'tenant_user',
            'required_permissions' => $this->metaPayload($payload),
            'status' => AssistantKnowledgeStatus::DRAFT,
            'version' => 1,
            'content_checksum' => hash('sha256', trim($payload['body'])),
            'published_at' => null,
            'last_indexed_at' => null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->audit($actor, 'assistant.knowledge.create', $source);

        return $source->fresh() ?? $source;
    }

    /**
     * @param  array{
     *   title?: string,
     *   body?: string,
     *   slug?: string|null,
     *   module_key?: string|null,
     *   audience?: string|null,
     *   required_permissions?: list<string>|null,
     *   related_routes?: list<string>|null
     * }  $payload
     */
    public function updateDraft(TenantUser $actor, AiKnowledgeSource $source, array $payload): AiKnowledgeSource
    {
        $this->assertTenantManaged($source);

        if ($source->status === AssistantKnowledgeStatus::ARCHIVED) {
            throw new RuntimeException('Archived knowledge sources cannot be edited. Create a new article instead.');
        }

        if (isset($payload['title'])) {
            $source->title = trim($payload['title']);
        }

        if (array_key_exists('slug', $payload) && is_string($payload['slug']) && trim($payload['slug']) !== '') {
            $source->slug = $this->resolveUniqueSlug($payload['slug'], $source->title, (string) $source->id);
        }

        if (array_key_exists('module_key', $payload)) {
            $source->module_key = $this->nullableString($payload['module_key']);
        }

        if (array_key_exists('audience', $payload)) {
            $source->audience = $this->nullableString($payload['audience']) ?? 'tenant_user';
        }

        if (isset($payload['body'])) {
            $source->body = trim($payload['body']);
            $source->content_checksum = hash('sha256', $source->body);
        }

        if (array_key_exists('required_permissions', $payload) || array_key_exists('related_routes', $payload)) {
            $existing = is_array($source->required_permissions) ? $source->required_permissions : [];
            $source->required_permissions = [
                'permissions' => array_key_exists('required_permissions', $payload)
                    ? $this->stringList($payload['required_permissions'] ?? [])
                    : ($existing['permissions'] ?? []),
                'related_routes' => array_key_exists('related_routes', $payload)
                    ? $this->stringList($payload['related_routes'] ?? [])
                    : ($existing['related_routes'] ?? []),
                'last_reviewed' => $existing['last_reviewed'] ?? null,
            ];
        }

        // Editing a published article returns it to draft until re-published.
        if ($source->status === AssistantKnowledgeStatus::PUBLISHED && $source->isDirty()) {
            $source->status = AssistantKnowledgeStatus::DRAFT;
        }

        $source->updated_by = $actor->id;
        $source->save();

        $this->audit($actor, 'assistant.knowledge.update', $source);

        return $source->fresh() ?? $source;
    }

    public function publish(TenantUser $actor, AiKnowledgeSource $source, bool $syncIngest = false): AiKnowledgeSource
    {
        $this->assertTenantManaged($source);

        $body = trim((string) $source->body);
        if ($body === '') {
            throw new RuntimeException('Cannot publish an empty knowledge article.');
        }

        $wasPublished = $source->published_at !== null;
        $source->status = AssistantKnowledgeStatus::PUBLISHED;
        $source->version = $wasPublished
            ? max(1, (int) $source->version) + 1
            : max(1, (int) $source->version);
        $source->published_at = now();
        $source->content_checksum = hash('sha256', $body);
        $source->updated_by = $actor->id;
        $source->save();

        $this->audit($actor, 'assistant.knowledge.publish', $source, [
            'version' => $source->version,
        ]);

        $this->dispatchIngest($actor, $source, $syncIngest);

        return $source->fresh() ?? $source;
    }

    public function archive(TenantUser $actor, AiKnowledgeSource $source): AiKnowledgeSource
    {
        $this->assertTenantManaged($source);

        return DB::transaction(function () use ($actor, $source): AiKnowledgeSource {
            $source->status = AssistantKnowledgeStatus::ARCHIVED;
            $source->updated_by = $actor->id;
            $source->save();

            AiKnowledgeChunk::query()->where('knowledge_source_id', $source->id)->delete();
            $source->last_indexed_at = null;
            $source->save();

            $this->audit($actor, 'assistant.knowledge.archive', $source);

            return $source->fresh() ?? $source;
        });
    }

    public function delete(TenantUser $actor, AiKnowledgeSource $source): void
    {
        $this->assertTenantManaged($source);

        $id = (string) $source->id;
        $title = $source->title;

        AiKnowledgeChunk::query()->where('knowledge_source_id', $source->id)->delete();
        $source->delete();

        $this->activity->record(
            module: 'ai_assistant',
            action: 'assistant.knowledge.delete',
            summary: 'Deleted knowledge source',
            entityType: 'ai_knowledge_source',
            entityId: $id,
            entityLabel: $title,
            actor: $actor,
        );
    }

    /**
     * @return array{source_id: string, chunks: int, deleted: int}|array{queued: true, source_id: string}
     */
    public function reindex(TenantUser $actor, AiKnowledgeSource $source, bool $sync = false): array
    {
        $this->assertTenantManaged($source);

        if ($source->status !== AssistantKnowledgeStatus::PUBLISHED) {
            throw new RuntimeException('Only published knowledge sources can be re-indexed.');
        }

        $this->audit($actor, 'assistant.knowledge.reindex', $source);

        if ($sync) {
            $result = $this->ingestion->ingestSource($source);
            $this->activity->record(
                module: 'ai_assistant',
                action: 'assistant.knowledge.ingest',
                summary: 'Knowledge source ingested',
                entityType: 'ai_knowledge_source',
                entityId: (string) $source->id,
                entityLabel: $source->title,
                actor: $actor,
                metadata: [
                    'slug' => $source->slug,
                    'scope' => $source->scope,
                    'version' => $source->version,
                    'chunks' => $result['chunks'],
                    'deleted' => $result['deleted'],
                    'sync' => true,
                ],
            );

            return $result;
        }

        $this->dispatchIngest($actor, $source, false);

        return [
            'queued' => true,
            'source_id' => (string) $source->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asListRow(AiKnowledgeSource $source): array
    {
        return [
            'id' => (string) $source->id,
            'slug' => $source->slug,
            'scope' => $source->scope,
            'title' => $source->title,
            'module_key' => $source->module_key,
            'source_type' => $source->source_type,
            'audience' => $source->audience,
            'status' => $source->status,
            'version' => (int) $source->version,
            'chunk_count' => (int) ($source->chunks_count ?? $source->chunks()->count()),
            'published_at' => $source->published_at?->toIso8601String(),
            'last_indexed_at' => $source->last_indexed_at?->toIso8601String(),
            'updated_at' => $source->updated_at?->toIso8601String(),
            'created_at' => $source->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asDetail(AiKnowledgeSource $source): array
    {
        $meta = is_array($source->required_permissions) ? $source->required_permissions : [];

        return [
            ...$this->asListRow($source),
            'body' => $source->body,
            'required_permissions' => array_values(array_map('strval', $meta['permissions'] ?? [])),
            'related_routes' => array_values(array_map('strval', $meta['related_routes'] ?? [])),
            'content_checksum' => $source->content_checksum,
            'created_by' => $source->created_by ? (string) $source->created_by : null,
            'updated_by' => $source->updated_by ? (string) $source->updated_by : null,
        ];
    }

    private function assertTenantManaged(AiKnowledgeSource $source): void
    {
        abort_unless($source->scope === AssistantKnowledgeScope::TENANT, 403, __('Global knowledge sources cannot be managed here.'));
    }

    private function dispatchIngest(TenantUser $actor, AiKnowledgeSource $source, bool $sync): void
    {
        $tenantId = tenant()?->getTenantKey();
        if (! is_string($tenantId) && ! is_int($tenantId)) {
            throw new RuntimeException('Tenant context is required to ingest knowledge.');
        }

        if ($sync) {
            $result = $this->ingestion->ingestSource($source);
            $this->activity->record(
                module: 'ai_assistant',
                action: 'assistant.knowledge.ingest',
                summary: 'Knowledge source ingested',
                entityType: 'ai_knowledge_source',
                entityId: (string) $source->id,
                entityLabel: $source->title,
                actor: $actor,
                metadata: [
                    'slug' => $source->slug,
                    'scope' => $source->scope,
                    'version' => $source->version,
                    'chunks' => $result['chunks'],
                    'deleted' => $result['deleted'],
                    'sync' => true,
                ],
            );

            return;
        }

        IngestKnowledgeSourceJob::dispatch((string) $tenantId, (string) $source->id);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(TenantUser $actor, string $action, AiKnowledgeSource $source, array $metadata = []): void
    {
        $this->activity->record(
            module: 'ai_assistant',
            action: $action,
            summary: $source->title,
            entityType: 'ai_knowledge_source',
            entityId: (string) $source->id,
            entityLabel: $source->title,
            actor: $actor,
            metadata: [
                'slug' => $source->slug,
                'status' => $source->status,
                'version' => $source->version,
                ...$metadata,
            ],
        );
    }

    private function resolveUniqueSlug(?string $requested, string $title, ?string $ignoreId = null): string
    {
        $base = Str::slug($requested !== null && trim($requested) !== '' ? $requested : $title);
        if ($base === '') {
            $base = 'article';
        }

        $slug = $base;
        $i = 2;
        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?string $ignoreId): bool
    {
        $query = AiKnowledgeSource::query()
            ->where('scope', AssistantKnowledgeScope::TENANT)
            ->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * @param  array{
     *   required_permissions?: list<string>|null,
     *   related_routes?: list<string>|null
     * }  $payload
     * @return array{permissions: list<string>, related_routes: list<string>, last_reviewed: null}
     */
    private function metaPayload(array $payload): array
    {
        return [
            'permissions' => $this->stringList($payload['required_permissions'] ?? []),
            'related_routes' => $this->stringList($payload['related_routes'] ?? []),
            'last_reviewed' => null,
        ];
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) || is_numeric($item)) {
                $trimmed = trim((string) $item);
                if ($trimmed !== '') {
                    $items[] = $trimmed;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
