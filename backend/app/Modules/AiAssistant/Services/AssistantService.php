<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\AskAssistantInput;
use App\Modules\AiAssistant\DTOs\AskAssistantResult;
use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;
use App\Modules\AiAssistant\DTOs\ToolPlan;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AiAssistant\Services\Actions\AssistantActionService;
use App\Modules\AiAssistant\Services\Tools\AssistantToolExecutor;
use App\Modules\AiAssistant\Services\Tools\AssistantToolFallbackPlanner;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRouter;
use App\Modules\AiAssistant\Support\AssistantAskStatus;
use App\Modules\AiAssistant\Support\AssistantChunkRanker;
use App\Modules\AiAssistant\Support\AssistantModuleSuggestionCatalog;
use App\Modules\AiAssistant\Support\AssistantPromptBuilder;
use App\Modules\AiAssistant\Support\AssistantProviderErrorClassifier;
use App\Modules\AiAssistant\Support\AssistantRelevanceGate;
use App\Modules\AiAssistant\Support\PromptSecurityService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tenant-aware assistant orchestration with RAG + optional read-only tools + grounded LLM.
 */
final class AssistantService
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly KnowledgeRetrievalService $retrieval,
        private readonly AssistantPromptBuilder $prompts,
        private readonly LlmProviderInterface $llm,
        private readonly PromptSecurityService $security,
        private readonly KnowledgeCatalogService $catalog,
        private readonly TenantEnabledModulesResolver $enabledModules,
        private readonly AssistantToolRouter $toolRouter,
        private readonly AssistantToolFallbackPlanner $toolFallback,
        private readonly AssistantToolExecutor $toolExecutor,
        private readonly AssistantActionService $actions,
        private readonly AssistantChunkRanker $chunkRanker = new AssistantChunkRanker,
        private readonly AssistantRelevanceGate $relevance = new AssistantRelevanceGate,
    ) {}

    public function ask(TenantUser $viewer, AskAssistantInput $input): AskAssistantResult
    {
        abort_unless((bool) config('ai_assistant.enabled', true), 503, __('AI Assistant is disabled.'));

        $safeQuestion = $this->security->sanitizeUserText($input->question);

        return DB::transaction(function () use ($viewer, $input, $safeQuestion): AskAssistantResult {
            $conversation = $this->conversations->findOrCreateForAsk($viewer, $input->conversationId, [
                'question' => $safeQuestion,
                'module_context' => $input->moduleContext,
                'page_path' => $input->pagePath,
            ]);

            if ($input->moduleContext !== null && $conversation->module_context === null) {
                $conversation->module_context = $input->moduleContext;
            }
            if ($input->pagePath !== null && $conversation->page_path === null) {
                $conversation->page_path = $input->pagePath;
            }

            $userMessage = $this->conversations->storeUserMessage($conversation, $safeQuestion);
            $history = $this->conversations->recentTurns($conversation, (string) $userMessage->id);

            $moduleContext = $input->moduleContext ?? $conversation->module_context;
            $pagePath = $input->pagePath ?? $conversation->page_path;

            // Stage 1: deterministic heuristic router.
            $plan = $this->toolRouter->plan($safeQuestion, $moduleContext);

            // Stage 2: allowlisted fallback when heuristics miss (follow-ups / module bias).
            if (! $plan->useTools()) {
                $fallback = $this->toolFallback->plan($safeQuestion, $moduleContext, $history);
                if ($fallback instanceof ToolPlan && $fallback->useTools()) {
                    $plan = $fallback;
                }
            }

            $toolResults = [];
            if ($plan->useTools()) {
                $toolResults = $this->toolExecutor->executeMany($viewer, $plan->calls);
            }

            $successfulTools = array_values(array_filter(
                $toolResults,
                static fn (ToolResult $r): bool => $r->ok,
            ));
            $usedLiveData = $successfulTools !== [];

            $chunks = [];
            $providerFailure = null;
            if ($plan->useDocs()) {
                try {
                    $chunks = $this->retrieval->retrieve($viewer, $safeQuestion, null, $moduleContext, $pagePath);
                    $chunks = $this->chunkRanker->rank($chunks, $safeQuestion, $moduleContext, $pagePath);
                    $chunks = $this->relevance->filter($chunks, $safeQuestion);
                    $chunks = $this->limitChunksForIntent($chunks, $safeQuestion);
                } catch (Throwable $e) {
                    $providerFailure = $this->providerFailureFrom($e);
                    if ($providerFailure === null) {
                        throw $e;
                    }

                    Log::warning('ai_assistant.ask.retrieval_failed', $this->security->sanitizeLogPayload([
                        'tenant_id' => tenant()?->getTenantKey(),
                        'user_id' => (string) $viewer->id,
                        'error' => $e->getMessage(),
                        'error_code' => $providerFailure['error_code'],
                    ]));
                }
            }

            $undocumentedModule = $this->undocumentedEnabledModule($moduleContext);
            $notes = [];
            if ($undocumentedModule !== null && $chunks === [] && ! $usedLiveData) {
                $notes[] = sprintf(
                    "The '%s' module is enabled for this tenant, but no help guide has been published yet. "
                    ."Tell the user honestly that the guide is not published yet and suggest a tenant admin publish one. Do not invent steps.",
                    $undocumentedModule,
                );
            }
            if ($usedLiveData) {
                $notes[] = 'Live system data was retrieved via allowlisted read-only tools. Prefer it for current operational facts.';
            }
            if ($history !== []) {
                $notes[] = 'Conversation history is included for follow-up resolution only.';
            }

            $prompt = $this->prompts->build(
                $safeQuestion,
                $chunks,
                $moduleContext,
                $pagePath,
                $notes,
                $toolResults,
                $history,
            );

            $errorCode = null;
            $providerNotice = null;

            if ($providerFailure !== null) {
                $status = $providerFailure['status'];
                $answer = $providerFailure['answer'];
                $modelName = $this->llm->modelName();
                $promptTokens = null;
                $completionTokens = null;
                $latencyMs = null;
                $followups = [];
                $errorCode = $providerFailure['error_code'];
                $providerNotice = $providerFailure['provider_notice'];
            } else {
                try {
                    $completion = $this->llm->complete($prompt);
                    $hasGrounding = $chunks !== [] || $usedLiveData;
                    $status = ($completion->insufficientContext || (! $hasGrounding && $toolResults === []))
                        ? AssistantAskStatus::INSUFFICIENT_CONTEXT
                        : AssistantAskStatus::COMPLETED;
                    $answer = $completion->answer;
                    if (! $hasGrounding && $undocumentedModule !== null) {
                        $answer = $this->guideNotPublishedAnswer($undocumentedModule);
                    }
                    $modelName = $completion->modelName;
                    $promptTokens = $completion->promptTokens;
                    $completionTokens = $completion->completionTokens;
                    $latencyMs = $completion->latencyMs;
                    $followups = $completion->suggestedFollowups;
                } catch (Throwable $e) {
                    Log::warning('ai_assistant.ask.llm_failed', $this->security->sanitizeLogPayload([
                        'tenant_id' => tenant()?->getTenantKey(),
                        'user_id' => (string) $viewer->id,
                        'error' => $e->getMessage(),
                    ]));

                    $providerFailure = $this->providerFailureFrom($e);
                    if ($providerFailure !== null) {
                        $status = $providerFailure['status'];
                        $answer = $providerFailure['answer'];
                        $errorCode = $providerFailure['error_code'];
                        $providerNotice = $providerFailure['provider_notice'];
                    } else {
                        $status = AssistantAskStatus::FAILED;
                        $answer = 'I could not generate an answer right now. Please try again in a moment.';
                    }

                    $modelName = $this->llm->modelName();
                    $promptTokens = null;
                    $completionTokens = null;
                    $latencyMs = null;
                    $followups = [];
                }
            }

            $docCitations = array_map(
                static fn (RetrievedKnowledgeChunk $chunk): array => $chunk->toCitationArray(),
                $chunks,
            );
            $liveCitations = array_map(
                static fn (ToolResult $result): array => $result->toCitationArray(),
                $successfulTools,
            );
            $citations = array_values(array_merge($liveCitations, $docCitations));

            $relatedLinks = $this->mergeRelatedLinks($chunks, $successfulTools);
            if ($followups === []) {
                $followups = $this->defaultFollowups($chunks, $usedLiveData, $moduleContext);
            }

            $assistantMessage = $this->conversations->storeAssistantMessage(
                conversation: $conversation,
                content: $answer,
                status: $status,
                citations: $citations,
                modelName: $modelName,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                latencyMs: $latencyMs,
            );

            $proposedAction = $this->actions->maybePropose(
                viewer: $viewer,
                question: $safeQuestion,
                moduleContext: $moduleContext,
                conversationId: (string) $conversation->id,
                messageId: (string) $assistantMessage->id,
            );

            if ($proposedAction !== null) {
                $status = AssistantAskStatus::COMPLETED;
                $answer = ($proposedAction['summary'] ?? 'I prepared an action for your review.')
                    ."\n\nNothing has been saved yet — confirm in the card below to proceed.";
                $assistantMessage->forceFill([
                    'content' => $answer,
                    'status' => $status,
                ])->save();
                $proposedAction['message_id'] = (string) $assistantMessage->id;
            }

            $conversation->forceFill([
                'last_message_at' => now(),
            ])->save();

            $this->conversations->recordAskAudit($viewer, $conversation, $assistantMessage);

            Log::info('ai_assistant.ask.completed', $this->security->sanitizeLogPayload([
                'tenant_id' => tenant()?->getTenantKey(),
                'user_id' => (string) $viewer->id,
                'conversation_id' => (string) $conversation->id,
                'message_id' => (string) $assistantMessage->id,
                'status' => $status,
                'model' => $modelName,
                'citation_count' => count($citations),
                'used_live_data' => $usedLiveData,
                'proposed_action' => $proposedAction['action'] ?? null,
                'tools' => array_map(static fn (ToolResult $r): string => $r->tool, $toolResults),
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'latency_ms' => $latencyMs,
            ]));

            return new AskAssistantResult(
                conversationId: (string) $conversation->id,
                messageId: (string) $assistantMessage->id,
                answer: $answer,
                citations: $citations,
                status: $status,
                suggestedFollowups: $followups,
                relatedLinks: $relatedLinks,
                modelName: $modelName,
                usedLiveData: $usedLiveData,
                proposedAction: $proposedAction,
                errorCode: $errorCode,
                providerNotice: $providerNotice,
            );
        });
    }

    /**
     * @return array{status: string, answer: string, error_code: string, provider_notice: array<string, string>}|null
     */
    private function providerFailureFrom(Throwable $e): ?array
    {
        $errorCode = AssistantProviderErrorClassifier::classify($e);
        if ($errorCode === null) {
            return null;
        }

        $notice = AssistantProviderErrorClassifier::noticeFor($errorCode);
        $answer = AssistantProviderErrorClassifier::chatAnswerFor($errorCode);
        if ($notice === null || $answer === null) {
            return null;
        }

        return [
            'status' => AssistantAskStatus::PROVIDER_QUOTA_EXCEEDED,
            'answer' => $answer,
            'error_code' => $errorCode,
            'provider_notice' => $notice,
        ];
    }

    private function undocumentedEnabledModule(?string $moduleContext): ?string
    {
        if ($moduleContext === null || $moduleContext === '') {
            return null;
        }

        $enabled = $this->enabledModules->resolveForCurrentTenant();
        if (! in_array($moduleContext, $enabled, true)) {
            return null;
        }

        $publishedModules = $this->catalog->publishedModuleKeysForCurrentTenant();

        return in_array($moduleContext, $publishedModules, true) ? null : $moduleContext;
    }

    private function guideNotPublishedAnswer(string $moduleKey): string
    {
        return sprintf(
            'The "%s" module is enabled for your tenant, but its help guide has not been published yet. '
            .'I will not invent steps. Ask a tenant admin to publish a help pack or a tenant knowledge article for this module.',
            $moduleKey,
        );
    }

    /**
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     * @param  list<ToolResult>  $tools
     * @return list<array{label: string, href: string}>
     */
    private function mergeRelatedLinks(array $chunks, array $tools): array
    {
        $links = [];
        $seen = [];

        foreach ($tools as $tool) {
            foreach ($tool->relatedRoutes as $href) {
                if (isset($seen[$href])) {
                    continue;
                }
                $seen[$href] = true;
                $links[] = [
                    'label' => 'Live: '.str_replace('_', ' ', $tool->tool),
                    'href' => $href,
                ];
            }
        }

        foreach ($chunks as $chunk) {
            foreach ($chunk->relatedRoutes as $href) {
                if (isset($seen[$href])) {
                    continue;
                }
                $seen[$href] = true;
                $links[] = [
                    'label' => $this->labelForRoute($href),
                    'href' => $href,
                ];
            }
        }

        return array_slice($links, 0, 8);
    }

    private function labelForRoute(string $href): string
    {
        $path = parse_url($href, PHP_URL_PATH) ?: $href;
        $path = rtrim($path, '/') ?: '/';

        return match (true) {
            $path === '/e-approval/submissions/new' => 'New E-Approval submission',
            $path === '/e-approval/submissions' => 'E-Approval submissions',
            str_starts_with($path, '/e-approval/approvals') => 'E-Approval approvals',
            $path === '/e-approval' => 'E-Approval',
            $path === '/documents/controlled' => 'Document register',
            default => $href,
        };
    }

    /**
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     * @return list<RetrievedKnowledgeChunk>
     */
    private function limitChunksForIntent(array $chunks, string $question): array
    {
        $intent = $this->chunkRanker->detectIntent($question);
        if ($intent === 'submit') {
            $filtered = array_values(array_filter(
                $chunks,
                fn (RetrievedKnowledgeChunk $chunk): bool => ! $this->chunkRanker->looksLikeApproveGuide($chunk),
            ));
            if ($filtered !== []) {
                $chunks = $filtered;
            }
        }

        return array_slice($chunks, 0, 3);
    }

    /**
     * @param  list<RetrievedKnowledgeChunk>  $chunks
     * @return list<string>
     */
    private function defaultFollowups(array $chunks, bool $usedLiveData, ?string $moduleContext): array
    {
        $catalog = new AssistantModuleSuggestionCatalog;
        $moduleSuggestions = $catalog->forModule($moduleContext);

        if ($chunks === [] && ! $usedLiveData) {
            return $moduleSuggestions;
        }

        if ($usedLiveData && $chunks === []) {
            return array_values(array_unique(array_merge([
                'What else is waiting for my approval?',
                'Show my open tickets',
                'Which documents expire soon?',
            ], array_slice($moduleSuggestions, 0, 1))));
        }

        $followups = [];
        foreach ($chunks as $chunk) {
            if ($chunk->title !== '') {
                $followups[] = 'Tell me more about: '.$chunk->title;
            }
            if (count($followups) >= 2) {
                break;
            }
        }

        foreach ($moduleSuggestions as $suggestion) {
            if (! in_array($suggestion, $followups, true)) {
                $followups[] = $suggestion;
            }
            if (count($followups) >= 3) {
                break;
            }
        }

        return array_slice($followups, 0, 3);
    }
}
