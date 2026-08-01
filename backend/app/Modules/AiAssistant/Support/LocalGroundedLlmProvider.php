<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\LlmCompletionResult;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;
use App\Modules\AiAssistant\DTOs\ToolResult;

/**
 * Local/dev LLM stand-in: builds concise grounded how-to answers from retrieved chunks + tool results.
 */
final class LocalGroundedLlmProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly AssistantChunkRanker $ranker = new AssistantChunkRanker,
        private readonly AssistantRelevanceGate $relevance = new AssistantRelevanceGate,
    ) {}

    public function complete(LlmPrompt $prompt): LlmCompletionResult
    {
        $started = hrtime(true);
        $question = $this->extractQuestion($prompt->user);
        $chunks = $this->ranker->rank($prompt->chunks, $question);
        $tools = array_values(array_filter(
            $prompt->toolResults,
            static fn ($r): bool => $r instanceof ToolResult && $r->ok,
        ));
        $toolFailures = array_values(array_filter(
            $prompt->toolResults,
            static fn ($r): bool => $r instanceof ToolResult && ! $r->ok,
        ));

        if ($chunks === [] && $tools === [] && $toolFailures !== []) {
            $answer = $this->toolUnavailableAnswer($toolFailures);

            return new LlmCompletionResult(
                answer: $answer,
                modelName: $this->modelName(),
                promptTokens: $this->estimateTokens($prompt->system.$prompt->user),
                completionTokens: $this->estimateTokens($answer),
                latencyMs: $this->elapsedMs($started),
                insufficientContext: false,
                suggestedFollowups: [
                    'What permissions do I need for E-Approval?',
                    'Where do I see my E-Approval submissions?',
                ],
            );
        }

        // Out-of-scope guard: if no live tool data and the best-matching help
        // article shares no meaningful keyword with the question, treat it as
        // outside the knowledge base instead of dumping the nearest doc.
        if ($tools === [] && $this->looksLikeControlledDocumentLookup($question)) {
            $answer = $this->controlledDocumentLookupUnavailableAnswer();

            return new LlmCompletionResult(
                answer: $answer,
                modelName: $this->modelName(),
                promptTokens: $this->estimateTokens($prompt->system.$prompt->user),
                completionTokens: $this->estimateTokens($answer),
                latencyMs: $this->elapsedMs($started),
                insufficientContext: true,
                suggestedFollowups: [
                    'How do I find a controlled document in the register?',
                    'How do I submit a Document Approval request?',
                ],
            );
        }

        if ($tools === [] && ($chunks === [] || ! $this->relevance->isRelevant($chunks[0], $question))) {
            $answer = $this->outOfScopeAnswer();

            return new LlmCompletionResult(
                answer: $answer,
                modelName: $this->modelName(),
                promptTokens: $this->estimateTokens($prompt->system.$prompt->user),
                completionTokens: $this->estimateTokens($answer),
                latencyMs: $this->elapsedMs($started),
                insufficientContext: true,
                suggestedFollowups: [
                    'How do I submit an E-Approval request?',
                    'How do I find a controlled document?',
                    'Why can’t I see a page or module?',
                ],
            );
        }

        $parts = [];

        if ($tools !== []) {
            $parts[] = 'From live system data:';
            $parts[] = '';
            foreach (array_slice($tools, 0, 2) as $tool) {
                $parts[] = '• '.$tool->summary;
                if ($tool->relatedRoutes !== []) {
                    $parts[] = '  Open: '.$tool->relatedRoutes[0];
                }
            }
            $parts[] = '';
        }

        if ($toolFailures !== []) {
            foreach (array_slice($toolFailures, 0, 2) as $tool) {
                $parts[] = 'Could not check '.str_replace('_', ' ', $tool->tool).': '.($tool->error ?? 'access denied.');
            }
            $parts[] = '';
        }

        if ($chunks !== [] && $this->shouldBlendDocsWithLiveTools($tools)) {
            $primary = $chunks[0];
            $intent = $this->ranker->detectIntent($question);
            $parts[] = match ($intent) {
                'track' => $this->synthesizeTrackAnswer($primary, $question),
                'returned' => $this->synthesizeReturnedAnswer($primary, $question),
                'form_missing' => $this->synthesizeFormMissingAnswer($primary, $question),
                default => $this->synthesizeHowTo($primary, $question),
            };

            if (! in_array($intent, ['track', 'returned', 'form_missing'], true)) {
                $secondary = $this->complementaryChunk($chunks, $primary, $question);
                if ($secondary !== null) {
                    $parts[] = '';
                    $parts[] = 'Also useful: '.$secondary->title.' — '.$this->oneLineSummary($secondary);
                }
            }
        }

        $answer = trim(implode("\n", $parts));
        $followups = $this->followupsForQuestion($question, $chunks, $tools);

        return new LlmCompletionResult(
            answer: $answer,
            modelName: $this->modelName(),
            promptTokens: $this->estimateTokens($prompt->system.$prompt->user),
            completionTokens: $this->estimateTokens($answer),
            latencyMs: $this->elapsedMs($started),
            insufficientContext: false,
            suggestedFollowups: $followups,
        );
    }

    public function modelName(): string
    {
        return 'local-grounded-v2';
    }

    private function synthesizeTrackAnswer(RetrievedKnowledgeChunk $chunk, string $question): string
    {
        $parts = [];
        $isDocumentApproval = str_contains(mb_strtolower($question), 'document approval')
            || str_contains(mb_strtolower($chunk->slug ?? ''), 'document-approval');

        $parts[] = $isDocumentApproval
            ? 'Track your Document Approval submission'
            : 'Track your E-Approval submission';
        $parts[] = '';

        $track = $this->sectionBody($chunk->body(), 'Track your submission');
        if ($track !== '') {
            $parts[] = $track;
        } else {
            $parts[] = '1. Open **E-Approval → Submissions**.';
            $parts[] = '2. Find your request by document number, form, or status.';
            $parts[] = '3. Open the submission to review workflow progress, comments, and attachments.';
            $parts[] = '4. Check **Notifications** for approval updates.';
        }

        $result = $this->sectionBody($chunk->body(), 'Expected result');
        if ($result !== '' && str_contains(mb_strtolower($result), 'workflow')) {
            $parts[] = '';
            $parts[] = 'Tip: '.$this->cleanExcerpt($result, 200);
        }

        return trim(implode("\n", $parts));
    }

    private function synthesizeReturnedAnswer(RetrievedKnowledgeChunk $chunk, string $question): string
    {
        $parts = [];
        $parts[] = 'If your submission was returned for revision';
        $parts[] = '';
        $parts[] = '1. Open **E-Approval → Submissions**.';
        $parts[] = '2. Find the returned request by document number, form, or status.';
        $parts[] = '3. Open it and read the approver remarks / return reason.';
        $parts[] = '4. Update the answers or attachments that need changes.';
        $parts[] = '5. **Resubmit** when ready so the workflow continues.';

        $errors = $this->sectionBody($chunk->body(), 'Common errors');
        if ($errors !== '') {
            foreach (preg_split('/\n+/', $errors) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '' && preg_match('/returned|revision|resubmit/i', $line) === 1) {
                    $parts[] = '';
                    $parts[] = 'From approved help: '.$line;
                    break;
                }
            }
        }

        $parts[] = '';
        $parts[] = 'Open: /e-approval/submissions';

        return trim(implode("\n", $parts));
    }

    private function synthesizeFormMissingAnswer(RetrievedKnowledgeChunk $chunk, string $question): string
    {
        $isDocumentControl = str_contains(mb_strtolower($question), 'document control')
            || str_contains(mb_strtolower($question), 'document approval')
            || str_contains(mb_strtolower($chunk->slug ?? ''), 'document-approval');

        $parts = [];
        $parts[] = $isDocumentControl
            ? 'If the Document Control form is not listed'
            : 'If the form you need is not listed';
        $parts[] = '';
        $parts[] = 'Usual causes:';
        $parts[] = '1. The form is **not published** for your tenant yet.';
        $parts[] = '2. You are looking at the wrong form family (pick **Document Control** / **ISO Document Control**, not another E-Approval form).';
        $parts[] = '3. You are missing access — you need `e_approval:submissions:create` (and usually `e_approval:view`).';
        $parts[] = '';
        $parts[] = 'What to do:';
        $parts[] = '1. Open **E-Approval → New submission** and search/filter for Document Control / ISO.';
        $parts[] = '2. If it still is not there, ask a tenant admin to **publish** the Document Control form and confirm your permissions.';
        $parts[] = '3. Do not start a different form for Document Approval — that creates the wrong workflow.';

        $errors = $this->sectionBody($chunk->body(), 'Common errors');
        if ($errors !== '') {
            foreach (preg_split('/\n+/', $errors) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '' && preg_match('/form not listed|not published|missing access|wrong form/i', $line) === 1) {
                    $parts[] = '';
                    $parts[] = 'From approved help: '.$line;
                    break;
                }
            }
        }

        $parts[] = '';
        $parts[] = 'Open: /e-approval/submissions/new';

        return trim(implode("\n", $parts));
    }

    private function synthesizeHowTo(RetrievedKnowledgeChunk $chunk, string $question): string
    {
        $body = $chunk->body();
        $parts = [];
        $parts[] = $chunk->title;
        $parts[] = '';

        $intro = $this->dropLeadingTitle($this->sectionBody($body, null), $chunk->title);
        if ($intro !== '' && ! $this->introEchoesTitle($intro, $chunk->title)) {
            $parts[] = $intro;
            $parts[] = '';
        }

        $prereqs = $this->sectionBody($body, 'Prerequisites');
        if ($prereqs !== '') {
            $parts[] = 'Prerequisites';
            $parts[] = $prereqs;
            $parts[] = '';
        }

        $steps = $this->sectionBody($body, 'Steps');
        if ($steps !== '') {
            $parts[] = 'Steps';
            $parts[] = $steps;
            $parts[] = '';
        } else {
            $excerpt = $this->cleanExcerpt($body, 700);
            if ($excerpt !== '') {
                $parts[] = $excerpt;
                $parts[] = '';
            }
        }

        $result = $this->sectionBody($body, 'Expected result');
        if ($result !== '') {
            $parts[] = 'Expected result';
            $parts[] = $result;
        }

        $answer = trim(implode("\n", $parts));

        if ($this->ranker->detectIntent($question) === 'submit' && $this->ranker->looksLikeApproveGuide($chunk)) {
            return "I found approval guidance, but your question looks like a submit/create request.\n\n".$answer;
        }

        return $answer;
    }

    /**
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     */
    private function complementaryChunk(
        array $chunks,
        RetrievedKnowledgeChunk $primary,
        string $question,
    ): ?RetrievedKnowledgeChunk {
        $intent = $this->ranker->detectIntent($question);

        foreach (array_slice($chunks, 1, 3) as $chunk) {
            if ($chunk->slug !== null && $chunk->slug !== '' && $chunk->slug === $primary->slug) {
                continue;
            }
            if ($intent === 'submit' && $this->ranker->looksLikeApproveGuide($chunk)) {
                continue;
            }

            return $chunk;
        }

        return null;
    }

    private function oneLineSummary(RetrievedKnowledgeChunk $chunk): string
    {
        $intro = $this->dropLeadingTitle($this->sectionBody($chunk->body(), null), $chunk->title);
        if ($intro !== '') {
            return $intro;
        }

        return $this->cleanExcerpt($chunk->body(), 140);
    }

    private function sectionBody(string $markdown, ?string $heading): string
    {
        $text = trim(str_replace("\r\n", "\n", $markdown));
        if ($text === '') {
            return '';
        }

        if ($heading === null) {
            // Everything before the first "## " section, with title/heading noise stripped.
            $parts = preg_split('/\n##\s/u', $text, 2);
            $intro = is_array($parts) && $parts !== [] ? $parts[0] : $text;

            return $this->cleanExcerpt(trim($this->stripNoise($intro)), 280);
        }

        $pattern = '/^##\s+'.preg_quote($heading, '/').'\s*\n+(.*?)(?=\n##\s|\z)/msui';
        if (preg_match($pattern, $text, $m) !== 1) {
            return '';
        }

        return trim($this->stripNoise(trim($m[1])));
    }

    private function dropLeadingTitle(string $text, string $title): string
    {
        $text = trim($text);
        $title = trim($title);
        if ($text === '' || $title === '') {
            return $text;
        }

        $lines = preg_split('/\n/u', $text) ?: [];
        while ($lines !== [] && mb_strtolower(trim($lines[0])) === mb_strtolower($title)) {
            array_shift($lines);
        }

        return trim(implode("\n", $lines));
    }

    private function cleanExcerpt(string $text, int $max): string
    {
        $clean = $this->stripNoise($text);
        $clean = trim(preg_replace("/\n{3,}/", "\n\n", $clean) ?? $clean);
        if (mb_strlen($clean) <= $max) {
            return $clean;
        }

        // Prefer cutting at a list-item boundary so steps are never half-finished.
        $slice = mb_substr($clean, 0, $max);
        if (preg_match('/\n(?=\d+\.\s|\-\s)/u', $slice, $m, PREG_OFFSET_CAPTURE) === 1) {
            $pos = $m[0][1];
            if ($pos > (int) ($max * 0.45)) {
                return rtrim(mb_substr($clean, 0, $pos));
            }
        }

        return rtrim($slice).'…';
    }

    private function stripNoise(string $text): string
    {
        $text = preg_replace('/^#+\s.*$/mu', '', $text) ?? $text;
        $text = preg_replace('/^---[\s\S]*?---\s*/u', '', $text) ?? $text;
        $text = preg_replace('/\n## Related workflows[\s\S]*$/ui', '', $text) ?? $text;
        $text = preg_replace('/\nRelated pages:.*$/mi', '', $text) ?? $text;
        $text = preg_replace('/\nSources:.*$/mi', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     * @param  list<ToolResult>  $tools
     * @return list<string>
     */
    private function followupsForQuestion(string $question, array $chunks, array $tools): array
    {
        $intent = $this->ranker->detectIntent($question);
        $followups = [];

        if ($intent === 'submit') {
            $followups[] = 'Where do I track my E-Approval submission?';
            $followups[] = 'What if my form is not listed?';
            $followups[] = 'What if my submission was returned for revision?';
        } elseif ($intent === 'form_missing') {
            $followups[] = 'How do I submit a Document Approval request?';
            $followups[] = 'How do I create an E-Approval request?';
            $followups[] = 'What permissions do I need for E-Approval?';
        } elseif ($intent === 'returned') {
            $followups[] = 'Where do I track my E-Approval submission?';
            $followups[] = 'What if my Document Control form is not listed?';
            $followups[] = 'How do I create an E-Approval request?';
        } elseif ($intent === 'track') {
            $followups[] = 'How do I submit a Document Approval request?';
            $followups[] = 'What if my submission was returned for revision?';
            $followups[] = 'What permissions do I need for E-Approval?';
        } elseif ($intent === 'approve') {
            $followups[] = 'What if my approval inbox is empty?';
            $followups[] = 'How do I return a submission for revision?';
        }

        foreach ($chunks as $chunk) {
            if ($intent === 'submit' && $this->ranker->looksLikeApproveGuide($chunk)) {
                continue;
            }
            if ($chunk->title !== '' && count($followups) < 3) {
                $candidate = 'Tell me more about: '.$chunk->title;
                if (! in_array($candidate, $followups, true)) {
                    $followups[] = $candidate;
                }
            }
            if (count($followups) >= 3) {
                break;
            }
        }

        if ($followups === [] && $tools !== []) {
            foreach ($tools as $tool) {
                foreach ($tool->relatedRoutes as $route) {
                    $followups[] = 'Open '.$route;
                    if (count($followups) >= 3) {
                        break 2;
                    }
                }
            }
        }

        return array_values(array_slice(array_unique($followups), 0, 3));
    }

    /**
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     */
    private function hasSlug(array $chunks, string $slug): bool
    {
        foreach ($chunks as $chunk) {
            if ($chunk->slug === $slug) {
                return true;
            }
        }

        return false;
    }

    private function extractQuestion(string $userPrompt): string
    {
        $normalized = str_replace("\r\n", "\n", $userPrompt);
        if (preg_match('/USER_QUESTION:\s*(.+?)(?:\n\nBEGIN_|\z)/su', $normalized, $m) === 1) {
            return trim($m[1]);
        }

        return trim($normalized);
    }

    private function insufficientAnswer(): string
    {
        return 'I do not have enough approved help content to answer that safely. '
            .'Try a more specific how-to question about a module you can access, '
            .'or ask a tenant admin if the relevant guide is not published yet. '
            .'I will not invent permissions or workflows.';
    }

    private function outOfScopeAnswer(): string
    {
        return "I’m the TowerOS workspace assistant, so I can only help with how-to and "
            ."process questions about your modules (E-Approval, Documents, Sites, Ticketing, "
            ."Project-One, and similar), or look up your own live items like submissions and "
            ."approvals.\n\nI can’t answer general questions outside TowerOS. Try asking, for "
            ."example, how to submit an E-Approval request, where to track a submission, or why "
            ."a page may be missing.";
    }

    /**
     * @param  list<ToolResult>  $tools
     */
    private function shouldBlendDocsWithLiveTools(array $tools): bool
    {
        foreach ($tools as $tool) {
            if (! $tool->ok) {
                continue;
            }

            if (in_array($tool->tool, [
                'get_ticket_by_number',
                'get_eapproval_submission_by_document_no',
                'get_controlled_document_by_code',
                'get_site_by_code',
                'list_my_eapproval_submissions',
                'list_my_pending_approvals',
                'list_my_open_tickets',
                'list_expiring_documents',
                'search_workspace_entities',
            ], true)) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeControlledDocumentLookup(string $question): bool
    {
        if (preg_match('/\bTKT-\d+/i', $question) === 1) {
            return false;
        }

        if (preg_match('/\b(?:status|revision|state)\s+of\s+[A-Z][A-Z0-9]*(?:-[A-Z0-9]+){2,}\b/i', $question) === 1) {
            return true;
        }

        return preg_match('/\b[A-Z]{2,}(?:-[A-Z0-9]+){3,}\b/', $question) === 1
            && preg_match('/\b(status|revision|state|current|what is|find|look\s*up|lookup|show|check)\b/i', $question) === 1;
    }

    private function controlledDocumentLookupUnavailableAnswer(): string
    {
        return "I could not look up that controlled document in the register. "
            ."Confirm you have **documents:controlled:view** and access to that document code, "
            ."then open **Document register** and search by code.";
    }

    private function introEchoesTitle(string $intro, string $title): bool
    {
        $normalizedIntro = mb_strtolower(preg_replace('/\s+/u', ' ', trim($intro)) ?? trim($intro));
        $normalizedTitle = mb_strtolower(preg_replace('/\s+/u', ' ', trim($title)) ?? trim($title));

        return $normalizedIntro === $normalizedTitle
            || str_starts_with($normalizedIntro, $normalizedTitle.'.')
            || str_starts_with($normalizedIntro, $normalizedTitle.' ');
    }

    /**
     * @param  list<ToolResult>  $toolFailures
     */
    private function toolUnavailableAnswer(array $toolFailures): string
    {
        $parts = ['I could not check the live system data for that request.'];

        foreach (array_slice($toolFailures, 0, 2) as $tool) {
            $parts[] = '- '.str_replace('_', ' ', $tool->tool).': '.($tool->error ?? 'access denied.');
        }

        $parts[] = '';
        $parts[] = 'Ask a tenant admin to confirm your permissions, or try a process/how-to question instead.';

        return implode("\n", $parts);
    }

    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }

    private function elapsedMs(int $startedHrtime): int
    {
        return (int) max(0, (hrtime(true) - $startedHrtime) / 1_000_000);
    }
}
