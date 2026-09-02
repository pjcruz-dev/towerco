<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\DTOs\ConversationTurn;
use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AiAssistant\Services\AiPromptModuleService;

final class AssistantPromptBuilder
{
    public function __construct(
        private readonly PromptSecurityService $security,
        private readonly AiPromptModuleService $promptModules,
    ) {}

    /**
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     * @param  list<string>  $notes
     * @param  list<ToolResult>  $toolResults
     * @param  list<ConversationTurn>  $history
     */
    public function build(
        string $question,
        array $chunks,
        ?string $moduleContext = null,
        ?string $pagePath = null,
        array $notes = [],
        array $toolResults = [],
        array $history = [],
        ?string $modelOverride = null,
    ): LlmPrompt {
        $safeQuestion = $this->security->sanitizeUserText($question);

        $historyUserTexts = [];
        foreach ($history as $turn) {
            if ($turn instanceof ConversationTurn && strtolower($turn->role) === 'user') {
                $historyUserTexts[] = $this->security->sanitizeUserText($turn->content);
            }
        }

        $system = $this->promptModules->assembleSystemPrompt($safeQuestion, $historyUserTexts, false);

        $contextBlocks = [];
        foreach ($chunks as $index => $chunk) {
            $n = $index + 1;
            $routes = $chunk->relatedRoutes !== []
                ? implode(', ', $chunk->relatedRoutes)
                : '(none)';
            $contextBlocks[] = <<<BLOCK
[DOC {$n}]
title: {$chunk->title}
slug: {$chunk->slug}
module: {$chunk->moduleKey}
version: {$chunk->version}
routes: {$routes}
content:
{$chunk->content}
BLOCK;
        }

        $contextSection = $contextBlocks === []
            ? '(no approved context documents were retrieved)'
            : implode("\n\n", $contextBlocks);

        $toolBlocks = [];
        foreach ($toolResults as $index => $result) {
            if (! $result instanceof ToolResult) {
                continue;
            }
            $n = $index + 1;
            $json = json_encode($result->toPromptArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $toolBlocks[] = "[LIVE {$n}]\n".($json !== false ? $json : '{}');
        }
        $liveSection = $toolBlocks === []
            ? '(no live system data tools were used)'
            : implode("\n\n", $toolBlocks);

        $historyBlocks = [];
        foreach ($history as $index => $turn) {
            if (! $turn instanceof ConversationTurn) {
                continue;
            }
            $n = $index + 1;
            $role = strtoupper($turn->role);
            $safeContent = $this->security->sanitizeUserText($turn->content);
            $historyBlocks[] = "[TURN {$n}] {$role}: {$safeContent}";
        }
        $historySection = $historyBlocks === []
            ? '(no prior turns)'
            : implode("\n", $historyBlocks);

        $moduleLine = $moduleContext !== null && $moduleContext !== '' ? $moduleContext : '(unknown)';
        $pageLine = $pagePath !== null && $pagePath !== '' ? $pagePath : '(unknown)';

        $notesSection = '';
        $cleanNotes = array_values(array_filter(array_map('trim', $notes), static fn (string $n): bool => $n !== ''));
        if ($cleanNotes !== []) {
            $notesSection = "\nSYSTEM_NOTES (trusted):\n- ".implode("\n- ", $cleanNotes)."\n";
        }

        $user = <<<USER
CURRENT_PAGE_MODULE: {$moduleLine}
CURRENT_PAGE_PATH: {$pageLine}
{$notesSection}
BEGIN_CONVERSATION_HISTORY
{$historySection}
END_CONVERSATION_HISTORY

USER_QUESTION:
{$safeQuestion}

BEGIN_LIVE_SYSTEM_DATA
{$liveSection}
END_LIVE_SYSTEM_DATA

BEGIN_UNTRUSTED_CONTEXT
{$contextSection}
END_UNTRUSTED_CONTEXT

Respond with a helpful answer grounded only in LIVE_SYSTEM_DATA and CONTEXT above. Use CONVERSATION_HISTORY only to resolve references in USER_QUESTION.
USER;

        return new LlmPrompt(
            system: $system,
            user: $user,
            chunks: $chunks,
            moduleContext: $moduleContext,
            pagePath: $pagePath,
            toolResults: $toolResults,
            modelOverride: $modelOverride,
        );
    }
}
