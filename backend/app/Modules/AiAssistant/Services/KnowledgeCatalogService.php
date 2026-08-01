<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\DTOs\GlobalHelpArticle;
use App\Modules\AiAssistant\Models\AiKnowledgeSource;
use App\Modules\AiAssistant\Support\AssistantKnowledgeScope;
use App\Modules\AiAssistant\Support\AssistantKnowledgeStatus;
use App\Modules\AiAssistant\Support\GlobalHelpFrontMatterParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Discovers codebase-versioned global help articles and registers them in ai_knowledge_sources.
 */
final class KnowledgeCatalogService
{
    public function __construct(
        private readonly GlobalHelpFrontMatterParser $parser,
        private readonly HelpPackDiscoveryService $helpPacks,
    ) {}

    public function globalKnowledgePath(): string
    {
        return app_path('Modules/AiAssistant/Knowledge/global');
    }

    /**
     * Core, cross-module global help articles shipped by the AiAssistant module.
     *
     * @return Collection<int, GlobalHelpArticle>
     */
    public function discoverGlobalArticles(): Collection
    {
        $directory = $this->globalKnowledgePath();
        if (! is_dir($directory)) {
            return collect();
        }

        $files = File::files($directory);

        return collect($files)
            ->filter(static function ($file): bool {
                $name = strtolower($file->getFilename());

                return str_ends_with($name, '.md') && $name !== 'readme.md';
            })
            ->map(function ($file): GlobalHelpArticle {
                $absolute = $file->getPathname();
                $relative = 'Knowledge/global/'.$file->getFilename();

                return $this->parser->parseFile($absolute, $relative);
            })
            ->sortBy(static fn (GlobalHelpArticle $article): string => $article->slug)
            ->values();
    }

    /**
     * Core global articles PLUS every module-shipped help pack, deduped by slug
     * (core global takes precedence on collision).
     *
     * @return Collection<int, GlobalHelpArticle>
     */
    public function discoverPublishableArticles(): Collection
    {
        $core = $this->discoverGlobalArticles();
        $seen = $core->map(static fn (GlobalHelpArticle $a): string => $a->slug)->all();

        $modulePacks = $this->helpPacks->discoverModuleHelpPacks()
            ->reject(static fn (GlobalHelpArticle $a): bool => in_array($a->slug, $seen, true));

        return $core
            ->concat($modulePacks)
            ->sortBy(static fn (GlobalHelpArticle $article): string => $article->slug)
            ->values();
    }

    /**
     * Distinct module keys that currently have a published knowledge source in
     * the active tenant database (used for honest "no guide yet" answers).
     *
     * @return list<string>
     */
    public function publishedModuleKeysForCurrentTenant(): array
    {
        return AiKnowledgeSource::query()
            ->where('status', AssistantKnowledgeStatus::PUBLISHED)
            ->whereNotNull('module_key')
            ->distinct()
            ->pluck('module_key')
            ->map(static fn ($key): string => (string) $key)
            ->all();
    }

    /**
     * Upsert discovered global + module-pack articles into the current tenant database.
     *
     * @return array{synced: int, created: int, updated: int, skipped: int}
     */
    public function syncGlobalSourcesToCurrentTenant(bool $pruneMissing = false): array
    {
        $articles = $this->discoverPublishableArticles();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $seenSlugs = [];

        foreach ($articles as $article) {
            if ($article->status !== AssistantKnowledgeStatus::PUBLISHED
                && $article->status !== 'published') {
                $skipped++;

                continue;
            }

            $seenSlugs[] = $article->slug;
            $existing = AiKnowledgeSource::query()
                ->where('scope', AssistantKnowledgeScope::GLOBAL)
                ->where('slug', $article->slug)
                ->first();

            $attributes = $this->attributesForArticle($article);

            if ($existing === null) {
                AiKnowledgeSource::query()->create($attributes);
                $created++;

                continue;
            }

            $existing->fill($attributes);
            if ($existing->isDirty()) {
                $existing->save();
                $updated++;
            } else {
                $skipped++;
            }
        }

        if ($pruneMissing) {
            AiKnowledgeSource::query()
                ->where('scope', AssistantKnowledgeScope::GLOBAL)
                ->whereNotNull('slug')
                ->when(
                    $seenSlugs !== [],
                    static fn ($q) => $q->whereNotIn('slug', $seenSlugs),
                    static fn ($q) => $q,
                )
                ->update([
                    'status' => AssistantKnowledgeStatus::ARCHIVED,
                ]);
        }

        return [
            'synced' => $created + $updated,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesForArticle(GlobalHelpArticle $article): array
    {
        $requiredPermissions = $article->permissions;
        $metadataRoutes = $article->relatedRoutes;

        return [
            'slug' => $article->slug,
            'scope' => AssistantKnowledgeScope::GLOBAL,
            'module_key' => $article->moduleKey,
            'title' => $article->title,
            'source_type' => 'markdown',
            'source_path' => $article->relativePath,
            'source_url' => null,
            'audience' => $article->audience,
            'required_permissions' => [
                'permissions' => $requiredPermissions,
                'related_routes' => $metadataRoutes,
                'last_reviewed' => $article->lastReviewed,
            ],
            'status' => AssistantKnowledgeStatus::PUBLISHED,
            'version' => $article->version,
            'content_checksum' => $article->contentChecksum,
            'published_at' => now(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
