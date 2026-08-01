<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;

/**
 * Lightweight intent-aware re-ranking for retrieved help chunks (local / grounded answers).
 * Also collapses multiple chunks from the same source to one (best-scoring) entry.
 */
final class AssistantChunkRanker
{
    /**
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     * @return list<RetrievedKnowledgeChunk>
     */
    public function rank(array $chunks, string $question, ?string $moduleContext = null, ?string $pagePath = null): array
    {
        if ($chunks === []) {
            return [];
        }

        $intent = $this->detectIntent($question);
        $docContext = $this->mentionsControlledDocuments($question);
        $module = is_string($moduleContext) ? trim($moduleContext) : '';
        $path = is_string($pagePath) ? trim($pagePath) : '';
        $scored = [];

        foreach ($chunks as $chunk) {
            $haystack = mb_strtolower(trim($chunk->title.' '.($chunk->slug ?? '').' '.$chunk->body()));
            $slug = mb_strtolower($chunk->slug ?? '');
            $score = $chunk->score;

            if ($module !== '' && $module !== 'core' && $chunk->moduleKey === $module) {
                $score += 0.22;
            }
            if ($chunk->scope === 'tenant') {
                $score += 0.12;
                if ($module !== '' && $chunk->moduleKey === $module) {
                    $score += 0.08;
                }
            }
            if ($path !== '') {
                foreach ($chunk->relatedRoutes as $route) {
                    if ($route !== '' && (str_starts_with($path, $route) || str_starts_with($route, $path))) {
                        $score += 0.15;
                        break;
                    }
                }
            }

            foreach ($this->questionTokens($question) as $token) {
                if (mb_strlen($token) < 3) {
                    continue;
                }
                if (str_contains($haystack, $token)) {
                    $score += 0.08;
                }
            }

            if ($intent === 'form_missing') {
                if ($slug === 'e-approval-form-not-listed'
                    || str_contains($haystack, 'form not listed')
                    || str_contains($haystack, 'common errors')
                    || str_contains($haystack, 'not published')) {
                    $score += 0.7;
                }
                if ($this->looksLikeSubmitGuide($chunk) || $slug === 'document-approval-submit-request') {
                    $score += 0.15;
                }
                if ($this->looksLikeApproveGuide($chunk)) {
                    $score -= 0.4;
                }
            }

            if ($intent === 'track') {
                if ($slug === 'e-approval-track-submission' || $this->looksLikeTrackGuide($chunk)) {
                    $score += 0.65;
                }
                if ($this->looksLikeSubmitGuide($chunk) && ! $this->looksLikeTrackGuide($chunk)) {
                    $score -= 0.15;
                }
                if ($docContext && $slug === 'document-approval-submit-request') {
                    $score += 0.2;
                }
            }

            if ($intent === 'returned') {
                if (str_contains($haystack, 'returned for revision')
                    || str_contains($haystack, 'common errors')
                    || str_contains($haystack, 'resubmit')) {
                    $score += 0.7;
                }
                if ($this->looksLikeApproveGuide($chunk)) {
                    $score -= 0.4;
                }
            }

            if ($intent === 'submit') {
                if ($this->looksLikeApproveGuide($chunk)) {
                    $score -= 0.45;
                }
                if ($this->looksLikeSubmitGuide($chunk)) {
                    $score += 0.4;
                }

                if ($docContext) {
                    if ($slug === 'document-approval-submit-request') {
                        $score += 0.6;
                    }
                    if (str_contains($haystack, 'document control') || str_contains($haystack, 'iso')) {
                        $score += 0.2;
                    }
                } else {
                    // Generic submit/create question — do not let the document-specific guide take over.
                    if ($slug === 'document-approval-submit-request') {
                        $score -= 0.35;
                    }
                    if ($slug === 'e-approval-create-request') {
                        $score += 0.35;
                    }
                }
            }

            if ($intent === 'approve') {
                if ($this->looksLikeApproveGuide($chunk)) {
                    $score += 0.55;
                }
                if ($this->looksLikeSubmitGuide($chunk) && ! $this->looksLikeApproveGuide($chunk)) {
                    $score -= 0.2;
                }
            }

            $scored[] = ['chunk' => $chunk, 'score' => $score];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        // Collapse to one chunk per source (highest score wins) so we never show
        // the same guide twice in sources or "also useful".
        $bySource = [];
        foreach ($scored as $row) {
            $sourceId = $row['chunk']->sourceId;
            if (! isset($bySource[$sourceId])) {
                $bySource[$sourceId] = $row['chunk'];
            }
        }

        return array_values($bySource);
    }

    public function detectIntent(string $question): string
    {
        $q = mb_strtolower($question);

        // Create/submit how-tos win over returned/revision phrasing that may appear
        // in retrieved help text if question extraction ever includes context.
        $isCreateOrSubmit = preg_match(
            '/\b(submit|create|raise|file|start|new request|how (do|to) (i |we )?(submit|create))\b/u',
            $q,
        ) === 1;

        if (preg_match('/\b(form|template)\b/u', $q) === 1
            && preg_match('/\b(not listed|not showing|missing|can\'?t find|cannot find|don\'?t see|does not appear|isn\'?t listed|no form)\b/u', $q) === 1) {
            return 'form_missing';
        }

        if (! $isCreateOrSubmit
            && preg_match('/\b(returned|return for|resubmit|returned for revision)\b/u', $q) === 1
            && preg_match('/\b(submission|request|approval|form|document)\b/u', $q) === 1) {
            return 'returned';
        }

        if (preg_match('/\b(where|track|tracking|status|follow[\s-]?up|check on|find my|monitor|view my)\b/u', $q) === 1
            && preg_match('/\b(submission|request|approval|document|ticket)\b/u', $q) === 1) {
            return 'track';
        }

        if (preg_match('/\b(approve|approver|awaiting me|approval inbox)\b/u', $q) === 1
            && preg_match('/\b(submit|create|raise|file|start)\b/u', $q) !== 1) {
            return 'approve';
        }

        if ($isCreateOrSubmit) {
            return 'submit';
        }

        if (str_contains($q, 'document approval') && preg_match('/\b(where|track|status)\b/u', $q) !== 1) {
            return 'submit';
        }

        return 'general';
    }

    public function mentionsControlledDocuments(string $question): bool
    {
        $q = mb_strtolower($question);

        return str_contains($q, 'document approval')
            || str_contains($q, 'document control')
            || str_contains($q, 'controlled document')
            || str_contains($q, 'iso')
            || str_contains($q, 'dcf');
    }

    public function looksLikeApproveGuide(RetrievedKnowledgeChunk $chunk): bool
    {
        $haystack = mb_strtolower($chunk->title.' '.($chunk->slug ?? ''));

        return str_contains($haystack, 'approve')
            || str_contains($haystack, 'approver')
            || str_contains($haystack, 'awaiting');
    }

    public function looksLikeSubmitGuide(RetrievedKnowledgeChunk $chunk): bool
    {
        $haystack = mb_strtolower($chunk->title.' '.($chunk->slug ?? ''));

        return str_contains($haystack, 'submit')
            || str_contains($haystack, 'create')
            || str_contains($haystack, 'new submission')
            || str_contains($haystack, 'document-approval-submit');
    }

    public function looksLikeTrackGuide(RetrievedKnowledgeChunk $chunk): bool
    {
        $haystack = mb_strtolower($chunk->title.' '.($chunk->slug ?? '').' '.$chunk->body());

        return str_contains($haystack, 'track your submission')
            || str_contains($haystack, 'track status')
            || (str_contains($haystack, 'submissions') && str_contains($haystack, 'track'));
    }

    /**
     * @return list<string>
     */
    private function questionTokens(string $question): array
    {
        $parts = preg_split('/[^a-z0-9]+/u', mb_strtolower($question)) ?: [];

        return array_values(array_filter($parts, static fn (string $t): bool => $t !== ''));
    }
}
