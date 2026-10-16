<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Contracts\LlmProviderInterface;
use App\Modules\AiAssistant\DTOs\AskAssistantInput;
use App\Modules\AiAssistant\DTOs\AskAssistantResult;
use App\Modules\AiAssistant\DTOs\ToolPlan;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AiAssistant\Models\AiAssistantProposedAction;
use App\Modules\AiAssistant\Models\AiConversation;
use App\Modules\AiAssistant\Services\Actions\AssistantActionService;
use App\Modules\AiAssistant\Services\Tools\AssistantToolExecutor;
use App\Modules\AiAssistant\Services\Tools\AssistantToolFallbackPlanner;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRouter;
use App\Modules\AiAssistant\Support\AssistantAskStatus;
use App\Modules\AiAssistant\Support\AssistantAudience;
use App\Modules\AiAssistant\Support\AssistantCostEstimator;
use App\Modules\AiAssistant\Support\AssistantModuleSuggestionCatalog;
use App\Modules\AiAssistant\Support\AssistantPromptBuilder;
use App\Modules\AiAssistant\Support\AssistantProviderErrorClassifier;
use App\Modules\AiAssistant\Support\AssistantRolePolicy;
use App\Modules\AiAssistant\Support\AssistantWorkLog;
use App\Modules\AiAssistant\Support\PromptSecurityService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tenant-aware assistant orchestration: catalog operations, live tools, and the API model.
 */
final class AssistantService
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly AssistantPromptBuilder $prompts,
        private readonly LlmProviderInterface $llm,
        private readonly PromptSecurityService $security,
        private readonly AssistantToolRouter $toolRouter,
        private readonly AssistantToolFallbackPlanner $toolFallback,
        private readonly AssistantToolExecutor $toolExecutor,
        private readonly AssistantActionService $actions,
        private readonly AssistantCatalogRunner $catalogRunner,
        private readonly AssistantRolePolicy $rolePolicy,
    ) {}

    private string $streamedThought = '';

    /**
     * @var list<array{id: string, kind: string, title: string, detail: string|null, status: string}>
     */
    private array $llmTrace = [];

    /**
     * @param  (callable(array{kind: string, text: string, name?: string, status?: string}): void)|null  $onDelta
     */
    public function ask(TenantUser $viewer, AskAssistantInput $input, ?callable $onProgress = null, ?callable $onDelta = null): AskAssistantResult
    {
        abort_unless((bool) config('ai_assistant.enabled', true), 503, __('AI Assistant is disabled.'));

        $safeQuestion = $this->security->sanitizeUserText($input->question);
        $this->streamedThought = '';
        $this->llmTrace = [];

        return DB::transaction(function () use ($viewer, $input, $safeQuestion, $onProgress, $onDelta): AskAssistantResult {
            $emit = function (array $delta) use ($onDelta): void {
                if (($delta['kind'] ?? '') === 'thinking') {
                    $this->streamedThought .= (string) ($delta['text'] ?? '');
                }
                if ($onDelta !== null) {
                    $onDelta($delta);
                }
            };

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

            $this->reportProgress($onProgress, 'question', 'model', 'Reading your question', 'done');

            if ($this->rolePolicy->blocksAssistant($viewer)) {
                return $this->finalizeActionValidationAsk(
                    viewer: $viewer,
                    conversation: $conversation,
                    message: 'The assistant is turned off for your role. An administrator can change that under Role Management, on the AI Assistant tab.',
                    modelName: $this->llm->modelName(),
                );
            }
            $roleNote = $this->rolePolicy->promptConstraint($viewer);

            $userMessage = $this->conversations->storeUserMessage($conversation, $safeQuestion);
            $history = $this->conversations->recentTurns($conversation, (string) $userMessage->id);

            $moduleContext = $input->moduleContext ?? $conversation->module_context;
            $pagePath = $input->pagePath ?? $conversation->page_path;

            $adminBuilder = AssistantAudience::isTenantAdmin($viewer);
            $catalogToolResults = null;

            // Tenant admins choose from the live catalog. Other users keep the sentence router.
            if ($adminBuilder && ! $input->planMode) {
                try {
                    $catalogRun = $this->catalogRunner->run(
                        viewer: $viewer,
                        question: $safeQuestion,
                        moduleContext: is_string($moduleContext) ? $moduleContext : null,
                        conversationId: (string) $conversation->id,
                        history: $history,
                        preferredModel: $input->preferredModel,
                        onProgress: function (array $step) use ($onProgress): void {
                            $this->rememberLlmStep($step);
                            if ($onProgress !== null) {
                                $onProgress($step);
                            }
                        },
                        onDelta: $emit,
                        roleNote: $roleNote,
                    );
                } catch (ValidationException $e) {
                    return $this->finalizeActionValidationAsk(
                        viewer: $viewer,
                        conversation: $conversation,
                        message: (string) collect($e->errors())->flatten()->first(),
                        modelName: (is_string($input->preferredModel) && $input->preferredModel !== '')
                            ? $input->preferredModel
                            : $this->llm->modelName(),
                    );
                } catch (Throwable $e) {
                    $providerFailure = $this->providerFailureFrom($e);
                    if ($providerFailure === null) {
                        throw $e;
                    }

                    return $this->finalizeProviderFailureAsk(
                        viewer: $viewer,
                        conversation: $conversation,
                        failure: $providerFailure,
                        modelName: $this->llm->modelName(),
                    );
                }

                if (is_string($catalogRun->executedSummary) && $catalogRun->executedSummary !== '') {
                    $this->reportProgress(
                        $onProgress,
                        'action',
                        'action',
                        'Saved the change',
                        'done',
                        $catalogRun->executedSummary,
                    );

                    return $this->finalizeActionValidationAsk(
                        viewer: $viewer,
                        conversation: $conversation,
                        message: $catalogRun->executedSummary,
                        modelName: (is_string($input->preferredModel) && $input->preferredModel !== '')
                            ? $input->preferredModel
                            : $this->llm->modelName(),
                    );
                }

                if ($catalogRun->proposedAction !== null) {
                    $this->reportProgress(
                        $onProgress,
                        'action',
                        'action',
                        'Prepared a change for you to confirm',
                        'done',
                        'Nothing has been saved yet.',
                    );

                    return $this->finalizeActionOnlyAsk(
                        viewer: $viewer,
                        conversation: $conversation,
                        proposedAction: $catalogRun->proposedAction,
                        modelName: (is_string($input->preferredModel) && $input->preferredModel !== '')
                            ? $input->preferredModel
                            : $this->llm->modelName(),
                    );
                }

                $catalogToolResults = $catalogRun->toolResults;
            }

            // Write actions (report builder, tickets, etc.) skip chat LLM — they use domain services directly.
            if ($catalogToolResults === null && ! $input->planMode) {
                try {
                    $earlyAction = $this->actions->maybePropose(
                        viewer: $viewer,
                        question: $safeQuestion,
                        moduleContext: $moduleContext,
                        conversationId: (string) $conversation->id,
                        messageId: null,
                        preferredModel: $input->preferredModel,
                    );
                } catch (ValidationException $e) {
                    return $this->finalizeActionValidationAsk(
                        viewer: $viewer,
                        conversation: $conversation,
                        message: (string) collect($e->errors())->flatten()->first(),
                        modelName: (is_string($input->preferredModel) && $input->preferredModel !== '')
                            ? $input->preferredModel
                            : $this->llm->modelName(),
                    );
                }
                if ($earlyAction !== null) {
                    $this->reportProgress(
                        $onProgress,
                        'action',
                        'action',
                        'Prepared a change for you to confirm',
                        'done',
                        'Nothing has been saved yet.',
                    );

                    return $this->finalizeActionOnlyAsk(
                        viewer: $viewer,
                        conversation: $conversation,
                        proposedAction: $earlyAction,
                        modelName: (is_string($input->preferredModel) && $input->preferredModel !== '')
                            ? $input->preferredModel
                            : $this->llm->modelName(),
                    );
                }
            }

            // Stage 1: deterministic heuristic router. Admin builder already chose from the catalog.
            if ($catalogToolResults !== null) {
                $plan = new ToolPlan(
                    $catalogToolResults !== [] ? ToolPlan::MODE_BOTH : ToolPlan::MODE_DOCS,
                );
            } else {
                $plan = $this->toolRouter->plan($safeQuestion, $moduleContext);
            }
            if ($plan->mode === ToolPlan::MODE_BOTH) {
                $plan = new ToolPlan(ToolPlan::MODE_TOOLS, $plan->calls);
            }

            // Stage 2: allowlisted fallback when heuristics miss (follow-ups / module bias).
            if ($catalogToolResults === null && ! $plan->useTools()) {
                $fallback = $this->toolFallback->plan($safeQuestion, $moduleContext, $history);
                if ($fallback instanceof ToolPlan && $fallback->useTools()) {
                    $plan = $fallback;
                }
            }

            $toolResults = $catalogToolResults ?? [];
            if ($catalogToolResults === null && $plan->useTools()) {
                foreach ($plan->calls as $index => $call) {
                    $this->reportProgress(
                        $onProgress,
                        'tool-'.$index,
                        'tool',
                        'Running '.$this->toolStepLabel($call->tool),
                        'running',
                    );
                }
                $toolResults = $this->toolExecutor->executeMany($viewer, $plan->calls);
                foreach ($toolResults as $index => $result) {
                    $detail = $result->ok
                        ? ($result->rowCount > 0 ? $result->rowCount.' '.Str::plural('row', $result->rowCount) : 'Finished.')
                        : trim((string) ($result->error ?? 'The tool did not finish.'));
                    $this->reportProgress(
                        $onProgress,
                        'tool-'.$index,
                        'tool',
                        'Ran '.$this->toolStepLabel($result->tool),
                        $result->ok ? 'done' : 'failed',
                        $detail !== '' ? $detail : null,
                    );
                }
            }

            $successfulTools = array_values(array_filter(
                $toolResults,
                static fn (ToolResult $r): bool => $r->ok,
            ));
            $usedLiveData = $successfulTools !== [];

            $chunks = [];
            $providerFailure = null;
            $notes = [];
            if ($usedLiveData) {
                $notes[] = 'Live system data was retrieved via allowlisted read-only tools. Prefer it for current operational facts.';
            }
            if ($history !== []) {
                $notes[] = 'Conversation history is included for follow-up resolution only.';
            }
            if ($input->planMode) {
                $notes[] = 'PLAN MODE: Reply with a clear numbered implementation / fix plan only. Do not claim work was executed. Do not propose write actions yet.';
            }
            if ($roleNote !== '') {
                $notes[] = $roleNote;
            }

            $prompt = $this->prompts->build(
                $safeQuestion,
                $chunks,
                $moduleContext,
                $pagePath,
                $notes,
                $toolResults,
                $history,
                $input->preferredModel,
                AssistantAudience::isTenantAdmin($viewer),
            );

            $errorCode = null;
            $providerNotice = null;
            $askedModel = $providerFailure === null;

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
                $modelLabel = $this->liveModelLabel($input);
                $this->reportProgress($onProgress, 'model', 'model', 'Asking '.$modelLabel, 'running', 'Starting');
                if ($this->llm instanceof CursorLlmProvider) {
                    $this->llm->listenToDeltas($emit);
                    $this->llm->listenWhileWaiting(function (string $status) use ($onProgress, $modelLabel): void {
                        $phase = match (strtoupper($status)) {
                            'CREATING' => 'Starting',
                            'RUNNING' => 'Working',
                            'FINISHED' => 'Finishing',
                            default => $status !== '' ? ucfirst(strtolower($status)) : 'Working',
                        };
                        $this->reportProgress(
                            $onProgress,
                            'model',
                            'model',
                            'Asking '.$modelLabel,
                            'running',
                            $phase,
                        );
                    });
                }
                try {
                    $completion = $this->llm->complete($prompt);
                    $status = AssistantAskStatus::COMPLETED;
                    $answer = $completion->answer;
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
                } finally {
                    if ($this->llm instanceof CursorLlmProvider) {
                        $this->llm->listenWhileWaiting(null);
                        $this->llm->listenToDeltas(null);
                    }
                }
            }

            if ($askedModel) {
                $modelFailedNow = ! in_array($status, [
                    AssistantAskStatus::COMPLETED,
                    AssistantAskStatus::INSUFFICIENT_CONTEXT,
                ], true);
                $this->reportProgress(
                    $onProgress,
                    'model',
                    'model',
                    $modelFailedNow ? 'Model request failed' : 'Asked '.$modelName,
                    $modelFailedNow ? 'failed' : 'done',
                    $modelFailedNow
                        ? null
                        : ($latencyMs !== null ? 'Finished in '.number_format($latencyMs / 1000, 1).'s' : 'Finished.'),
                );
            }

            $liveCitations = array_map(
                static fn (ToolResult $result): array => $result->toCitationArray(),
                $successfulTools,
            );
            $citations = array_values($liveCitations);

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

            $proposedAction = null;
            if ($catalogToolResults === null && ! $input->planMode) {
                $proposedAction = $this->actions->maybePropose(
                    viewer: $viewer,
                    question: $safeQuestion,
                    moduleContext: $moduleContext,
                    conversationId: (string) $conversation->id,
                    messageId: (string) $assistantMessage->id,
                    preferredModel: $input->preferredModel,
                );

                if ($proposedAction !== null) {
                    $this->reportProgress(
                        $onProgress,
                        'action',
                        'action',
                        'Prepared a change for you to confirm',
                        'done',
                        'Nothing has been saved yet.',
                    );
                    $status = AssistantAskStatus::COMPLETED;
                    $answer = ($proposedAction['summary'] ?? 'I prepared an action for your review.')
                        ."\n\nNothing has been saved yet — confirm in the card below to proceed.";
                    $assistantMessage->forceFill([
                        'content' => $answer,
                        'status' => $status,
                    ])->save();
                    $proposedAction['message_id'] = (string) $assistantMessage->id;
                }
            }

            $activity = AssistantWorkLog::build(
                searched: false,
                searchFailed: false,
                chunkTitles: [],
                toolResults: $toolResults,
                modelName: $askedModel ? $modelName : null,
                latencyMs: $latencyMs,
                modelFailed: $askedModel && ! in_array($status, [
                    AssistantAskStatus::COMPLETED,
                    AssistantAskStatus::INSUFFICIENT_CONTEXT,
                ], true),
                proposedAction: $proposedAction,
            );
            $activity = $this->withThought($activity);
            $assistantMessage->forceFill(['activity' => $activity])->save();

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
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                costEstimate: AssistantCostEstimator::estimate(
                    $promptTokens,
                    $completionTokens,
                    $prompt->user ?? $safeQuestion,
                    $answer,
                    $modelName,
                ),
                activity: $activity,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $proposedAction
     */
    private function finalizeActionOnlyAsk(
        TenantUser $viewer,
        AiConversation $conversation,
        array $proposedAction,
        string $modelName,
    ): AskAssistantResult {
        $answer = ($proposedAction['summary'] ?? 'I prepared an action for your review.')
            ."\n\nNothing has been saved yet — confirm in the card below to proceed.";

        $activity = AssistantWorkLog::build(
            searched: false,
            searchFailed: false,
            chunkTitles: [],
            toolResults: [],
            modelName: null,
            latencyMs: null,
            modelFailed: false,
            proposedAction: $proposedAction,
        );
        $activity = $this->withThought($activity);

        $assistantMessage = $this->conversations->storeAssistantMessage(
            conversation: $conversation,
            content: $answer,
            status: AssistantAskStatus::COMPLETED,
            citations: [],
            modelName: $modelName,
            activity: $activity,
        );

        AiAssistantProposedAction::query()
            ->where('id', (string) ($proposedAction['id'] ?? ''))
            ->update(['message_id' => $assistantMessage->id]);

        $proposedAction['message_id'] = (string) $assistantMessage->id;

        $conversation->forceFill([
            'last_message_at' => now(),
        ])->save();

        $this->conversations->recordAskAudit($viewer, $conversation, $assistantMessage);

        Log::info('ai_assistant.ask.completed', $this->security->sanitizeLogPayload([
            'tenant_id' => tenant()?->getTenantKey(),
            'user_id' => (string) $viewer->id,
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $assistantMessage->id,
            'status' => AssistantAskStatus::COMPLETED,
            'model' => $modelName,
            'citation_count' => 0,
            'used_live_data' => false,
            'proposed_action' => $proposedAction['action'] ?? null,
            'tools' => [],
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'latency_ms' => null,
        ]));

        return new AskAssistantResult(
            conversationId: (string) $conversation->id,
            messageId: (string) $assistantMessage->id,
            answer: $answer,
            citations: [],
            status: AssistantAskStatus::COMPLETED,
            suggestedFollowups: [],
            relatedLinks: [],
            modelName: $modelName,
            usedLiveData: false,
            proposedAction: $proposedAction,
            activity: $activity,
        );
    }

    /**
     * @param  array{status: string, answer: string, error_code: string, provider_notice: array<string, string>}  $failure
     */
    private function finalizeProviderFailureAsk(
        TenantUser $viewer,
        AiConversation $conversation,
        array $failure,
        string $modelName,
    ): AskAssistantResult {
        $activity = AssistantWorkLog::build(
            searched: false,
            searchFailed: false,
            chunkTitles: [],
            toolResults: [],
            modelName: $modelName,
            latencyMs: null,
            modelFailed: true,
            blockedDetail: $failure['answer'],
        );
        $activity = $this->withThought($activity);

        $assistantMessage = $this->conversations->storeAssistantMessage(
            conversation: $conversation,
            content: $failure['answer'],
            status: $failure['status'],
            citations: [],
            modelName: $modelName,
            activity: $activity,
        );

        $conversation->forceFill([
            'last_message_at' => now(),
        ])->save();

        $this->conversations->recordAskAudit($viewer, $conversation, $assistantMessage);

        return new AskAssistantResult(
            conversationId: (string) $conversation->id,
            messageId: (string) $assistantMessage->id,
            answer: $failure['answer'],
            citations: [],
            status: $failure['status'],
            suggestedFollowups: [],
            relatedLinks: [],
            modelName: $modelName,
            usedLiveData: false,
            proposedAction: null,
            errorCode: $failure['error_code'],
            providerNotice: $failure['provider_notice'],
            activity: $activity,
        );
    }

    /**
     * @param  list<ToolResult>  $toolResults
     */
    private function finalizeActionValidationAsk(
        TenantUser $viewer,
        AiConversation $conversation,
        string $message,
        string $modelName,
        array $toolResults = [],
        bool $usedLiveData = false,
    ): AskAssistantResult {
        $answer = trim($message) !== ''
            ? $message
            : 'I could not prepare that action. Check the email and role name, then try again.';

        $activity = AssistantWorkLog::build(
            searched: false,
            searchFailed: false,
            chunkTitles: [],
            toolResults: $toolResults,
            modelName: $toolResults === [] ? null : $modelName,
            latencyMs: null,
            modelFailed: false,
            blockedDetail: $toolResults === [] ? $answer : null,
        );
        $activity = $this->withThought($activity);

        $citations = [];
        if ($usedLiveData) {
            foreach ($toolResults as $result) {
                if ($result->ok) {
                    $citations[] = $result->toCitationArray();
                }
            }
        }

        $assistantMessage = $this->conversations->storeAssistantMessage(
            conversation: $conversation,
            content: $answer,
            status: AssistantAskStatus::COMPLETED,
            citations: $citations,
            modelName: $modelName,
            activity: $activity,
        );

        $conversation->forceFill([
            'last_message_at' => now(),
        ])->save();

        $this->conversations->recordAskAudit($viewer, $conversation, $assistantMessage);

        return new AskAssistantResult(
            conversationId: (string) $conversation->id,
            messageId: (string) $assistantMessage->id,
            answer: $answer,
            citations: $citations,
            status: AssistantAskStatus::COMPLETED,
            suggestedFollowups: [],
            relatedLinks: [],
            modelName: $modelName,
            usedLiveData: $usedLiveData,
            proposedAction: null,
            activity: $activity,
        );
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

    /**
     * @param  list<ToolResult>  $tools
     * @return list<array{label: string, href: string}>
     */
    private function mergeRelatedLinks(array $chunks, array $tools): array
    {
        unset($chunks);
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

        return array_slice($links, 0, 8);
    }

    /**
     * @param  list<mixed>  $chunks
     * @return list<string>
     */
    private function defaultFollowups(array $chunks, bool $usedLiveData, ?string $moduleContext): array
    {
        unset($chunks);
        $catalog = new AssistantModuleSuggestionCatalog;
        $moduleSuggestions = $catalog->forModule($moduleContext);

        if (! $usedLiveData) {
            return $moduleSuggestions;
        }

        return array_values(array_unique(array_merge([
            'What else is waiting for my approval?',
            'Show my open tickets',
            'How do I create an E-Forms request?',
        ], array_slice($moduleSuggestions, 0, 1))));
    }

    private function liveModelLabel(AskAssistantInput $input): string
    {
        $preferred = is_string($input->preferredModel) ? trim($input->preferredModel) : '';

        return $preferred !== '' ? $preferred : $this->llm->modelName();
    }

    private function toolStepLabel(string $tool): string
    {
        $label = trim(Str::headline(str_replace('_', ' ', $tool)));

        return $label !== '' ? $label : $tool;
    }

    /**
     * @param  (callable(array{id: string, kind: string, title: string, status: string, detail: string|null}): void)|null  $onProgress
     */
    /**
     * @param  list<array{id: string, kind: string, title: string, detail: string|null, status: string}>  $activity
     * @return list<array{id: string, kind: string, title: string, detail: string|null, status: string}>
     */
    private function withThought(array $activity): array
    {
        $trace = array_values(array_filter(
            $this->llmTrace,
            static fn (array $step): bool => ($step['status'] ?? '') !== 'running',
        ));
        if ($this->traceIsLlmWork($trace)) {
            if (! $this->traceHasThought($trace)) {
                $text = $this->proseThought($this->streamedThought);
                if ($text !== '') {
                    array_unshift($trace, [
                        'id' => 'thought',
                        'kind' => 'thought',
                        'title' => 'Thinking',
                        'detail' => $text,
                        'status' => 'done',
                    ]);
                }
            }

            return $trace;
        }

        $text = $this->proseThought($this->streamedThought);
        if ($text === '') {
            return $activity;
        }

        array_unshift($activity, [
            'id' => 'thought',
            'kind' => 'thought',
            'title' => 'Thinking',
            'detail' => $text,
            'status' => 'done',
        ]);

        return $activity;
    }

    /**
     * @param  array{id: string, kind: string, title: string, status: string, detail: string|null}  $step
     */
    private function rememberLlmStep(array $step): void
    {
        $kind = (string) ($step['kind'] ?? '');
        if (! in_array($kind, ['thought', 'tool', 'action'], true)) {
            return;
        }
        $id = (string) ($step['id'] ?? '');
        if ($id === '') {
            return;
        }

        foreach ($this->llmTrace as $index => $existing) {
            if ($existing['id'] === $id) {
                $this->llmTrace[$index] = [
                    'id' => $id,
                    'kind' => $kind,
                    'title' => (string) ($step['title'] ?? ''),
                    'detail' => isset($step['detail']) ? (is_string($step['detail']) ? $step['detail'] : null) : null,
                    'status' => (string) ($step['status'] ?? 'done'),
                ];

                return;
            }
        }

        $this->llmTrace[] = [
            'id' => $id,
            'kind' => $kind,
            'title' => (string) ($step['title'] ?? ''),
            'detail' => isset($step['detail']) ? (is_string($step['detail']) ? $step['detail'] : null) : null,
            'status' => (string) ($step['status'] ?? 'done'),
        ];
    }

    /**
     * @param  list<array{id: string, kind: string, title: string, detail: string|null, status: string}>  $trace
     */
    private function traceIsLlmWork(array $trace): bool
    {
        foreach ($trace as $step) {
            if (in_array($step['kind'], ['tool', 'thought', 'action'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{id: string, kind: string, title: string, detail: string|null, status: string}>  $trace
     */
    private function traceHasThought(array $trace): bool
    {
        foreach ($trace as $step) {
            if ($step['kind'] === 'thought' && trim((string) ($step['detail'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function proseThought(string $text): string
    {
        $lines = preg_split('/\R/', trim($text)) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '```') || str_starts_with($line, '{') || str_starts_with($line, '[')) {
                continue;
            }
            if (preg_match('/^\s*(select|insert|update|delete|with)\b/i', $line) === 1) {
                continue;
            }
            $kept[] = $line;
        }

        $prose = trim(implode("\n", $kept));
        if ($prose === '') {
            return '';
        }
        if (mb_strlen($prose) > 1200) {
            return mb_substr($prose, 0, 1199).'…';
        }

        return $prose;
    }

    private function reportProgress(
        ?callable $onProgress,
        string $id,
        string $kind,
        string $title,
        string $status,
        ?string $detail = null,
    ): void {
        if ($onProgress === null) {
            return;
        }

        $onProgress([
            'id' => $id,
            'kind' => $kind,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ]);
    }
}
