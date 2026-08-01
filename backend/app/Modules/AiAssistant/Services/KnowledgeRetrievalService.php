<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Contracts\EmbeddingProviderInterface;
use App\Modules\AiAssistant\Contracts\VectorStoreInterface;
use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;
use App\Modules\AiAssistant\Support\AssistantKnowledgeScope;
use App\Modules\AiAssistant\Support\AssistantKnowledgeStatus;
use App\Modules\AiAssistant\Support\AssistantRelevanceGate;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;

final class KnowledgeRetrievalService
{
    public function __construct(
        private readonly EmbeddingProviderInterface $embeddings,
        private readonly VectorStoreInterface $vectors,
        private readonly TenantEnabledModulesResolver $enabledModules,
        private readonly AssistantRelevanceGate $relevance = new AssistantRelevanceGate,
    ) {}

    /**
     * @return list<RetrievedKnowledgeChunk>
     */
    public function retrieve(
        TenantUser $viewer,
        string $query,
        ?int $topK = null,
        ?string $moduleContext = null,
        ?string $pagePath = null,
    ): array {
        $question = trim($query);
        if ($question === '') {
            return [];
        }

        $limit = max(1, $topK ?? (int) config('ai_assistant.retrieval.top_k', 5));
        $minScore = (float) config('ai_assistant.retrieval.min_score', 0.05);
        $enabled = $this->enabledModules->resolveForCurrentTenant();

        $queryEmbedding = $this->embeddings->embed($question);

        $candidates = $this->vectors->search($queryEmbedding, [
            'scopes' => [AssistantKnowledgeScope::GLOBAL, AssistantKnowledgeScope::TENANT],
            'status' => AssistantKnowledgeStatus::PUBLISHED,
            'module_keys' => $enabled,
            'top_k' => max($limit * 4, 20),
            'tenant_id' => tenant()?->getTenantKey(),
        ]);

        $filtered = [];
        foreach ($candidates as $candidate) {
            if ($candidate->score < $minScore) {
                continue;
            }

            if (! $this->moduleAllowed($candidate->moduleKey, $enabled)) {
                continue;
            }

            if (! $this->permissionsAllowed($viewer, $candidate->permissions)) {
                continue;
            }

            $filtered[] = $candidate;
        }

        // Lexical + combined score gate (rejects weak nearest neighbors).
        $filtered = $this->relevance->filter($filtered, $question);
        $filtered = $this->preferModuleAndTenant($filtered, $moduleContext, $pagePath);

        return array_slice($filtered, 0, $limit);
    }

    /**
     * Prefer help for the current page module and tenant SOPs over unrelated modules.
     *
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     * @return list<RetrievedKnowledgeChunk>
     */
    private function preferModuleAndTenant(array $chunks, ?string $moduleContext, ?string $pagePath): array
    {
        if ($chunks === []) {
            return [];
        }

        $module = is_string($moduleContext) ? trim($moduleContext) : '';
        $path = is_string($pagePath) ? trim($pagePath) : '';

        usort($chunks, function (RetrievedKnowledgeChunk $a, RetrievedKnowledgeChunk $b) use ($module, $path): int {
            return $this->biasedScore($b, $module, $path) <=> $this->biasedScore($a, $module, $path);
        });

        return $chunks;
    }

    private function biasedScore(RetrievedKnowledgeChunk $chunk, string $module, string $pagePath): float
    {
        $score = $chunk->score;

        if ($module !== '' && $module !== 'core' && $chunk->moduleKey === $module) {
            $score += 0.28;
        }

        if ($chunk->scope === AssistantKnowledgeScope::TENANT) {
            $score += 0.12;
            if ($module !== '' && $chunk->moduleKey === $module) {
                $score += 0.1;
            }
        }

        if ($pagePath !== '') {
            foreach ($chunk->relatedRoutes as $route) {
                if ($route !== '' && (str_starts_with($pagePath, $route) || str_starts_with($route, $pagePath))) {
                    $score += 0.18;
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * @param  list<string>  $enabledModules
     */
    private function moduleAllowed(?string $moduleKey, array $enabledModules): bool
    {
        if ($moduleKey === null || $moduleKey === '') {
            return true;
        }

        return in_array($moduleKey, $enabledModules, true);
    }

    /**
     * @param  list<string>  $requiredPermissions
     */
    private function permissionsAllowed(TenantUser $viewer, array $requiredPermissions): bool
    {
        if ($requiredPermissions === []) {
            return true;
        }

        foreach ($requiredPermissions as $permission) {
            if (! $viewer->can($permission)) {
                return false;
            }
        }

        return true;
    }
}
