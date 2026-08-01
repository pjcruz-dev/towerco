<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\DTOs\ConversationTurn;
use App\Modules\AiAssistant\Models\AiAssistantProposedAction;
use App\Modules\AiAssistant\Models\AiConversation;
use App\Modules\AiAssistant\Models\AiMessage;
use App\Modules\AiAssistant\Services\Actions\AssistantActionService;
use App\Modules\AiAssistant\Support\AssistantAskStatus;
use App\Modules\AiAssistant\Support\AssistantConversationStatus;
use App\Modules\AiAssistant\Support\AssistantMessageRole;
use App\Modules\AiAssistant\Support\AssistantProposedActionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class ConversationService
{
    public function __construct(
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @param  array{
     *   question: string,
     *   module_context?: string|null,
     *   page_path?: string|null
     * }  $context
     */
    public function findOrCreateForAsk(TenantUser $viewer, ?string $conversationId, array $context): AiConversation
    {
        if ($conversationId !== null && $conversationId !== '') {
            $conversation = AiConversation::query()->find($conversationId);
            abort_if($conversation === null, 404, __('Conversation not found.'));
            abort_unless($this->canContinue($viewer, $conversation), 403);

            return $conversation;
        }

        $title = $this->titleFromQuestion($context['question']);

        return AiConversation::query()->create([
            'user_id' => $viewer->id,
            'title' => $title,
            'module_context' => $context['module_context'] ?? null,
            'page_path' => $context['page_path'] ?? null,
            'status' => AssistantConversationStatus::ACTIVE,
            'last_message_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>|null  $citations
     */
    public function storeUserMessage(AiConversation $conversation, string $content): AiMessage
    {
        return $this->storeMessage(
            conversation: $conversation,
            role: AssistantMessageRole::USER,
            content: $content,
            status: AssistantAskStatus::COMPLETED,
        );
    }

    /**
     * Prior turns for the ask (excludes the just-stored current user message).
     *
     * @return list<ConversationTurn>
     */
    public function recentTurns(AiConversation $conversation, ?string $excludeMessageId = null, ?int $limit = null): array
    {
        $max = max(0, $limit ?? (int) config('ai_assistant.conversation.history_turns', 6));
        if ($max === 0) {
            return [];
        }

        $query = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($max + ($excludeMessageId !== null ? 1 : 0));

        $rows = $query->get();
        $turns = [];
        foreach ($rows as $message) {
            if ($excludeMessageId !== null && (string) $message->id === $excludeMessageId) {
                continue;
            }

            $content = trim((string) $message->content);
            if ($content === '') {
                continue;
            }

            // Cap each turn so prompts stay bounded.
            $turns[] = new ConversationTurn(
                role: (string) $message->role,
                content: Str::limit($content, 600, '…'),
            );
            if (count($turns) >= $max) {
                break;
            }
        }

        return array_reverse($turns);
    }

    /**
     * @param  list<array<string, mixed>>|null  $citations
     */
    public function storeAssistantMessage(
        AiConversation $conversation,
        string $content,
        string $status,
        ?array $citations = null,
        ?string $modelName = null,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        ?int $latencyMs = null,
    ): AiMessage {
        return $this->storeMessage(
            conversation: $conversation,
            role: AssistantMessageRole::ASSISTANT,
            content: $content,
            status: $status,
            citations: $citations,
            modelName: $modelName,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            latencyMs: $latencyMs,
        );
    }

    public function touchConversation(AiConversation $conversation): void
    {
        $conversation->forceFill([
            'last_message_at' => now(),
        ])->save();
    }

    public function recordAskAudit(TenantUser $viewer, AiConversation $conversation, AiMessage $assistantMessage): void
    {
        $this->activity->record(
            module: 'ai_assistant',
            action: 'assistant.ask',
            summary: 'Assistant ask',
            entityType: 'ai_conversation',
            entityId: (string) $conversation->id,
            entityLabel: $conversation->title,
            actor: $viewer,
            metadata: [
                'message_id' => (string) $assistantMessage->id,
                'status' => $assistantMessage->status,
                'module_context' => $conversation->module_context,
                'page_path' => $conversation->page_path,
            ],
        );
    }

    /**
     * @return LengthAwarePaginator<int, AiConversation>
     */
    public function paginateForViewer(TenantUser $viewer, int $page = 1, int $perPage = 25, string $search = ''): LengthAwarePaginator
    {
        $query = AiConversation::query()
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');

        $this->scopeVisibleTo($query, $viewer);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function (Builder $inner) use ($like): void {
                $inner->where('title', 'like', $like)
                    ->orWhere('module_context', 'like', $like)
                    ->orWhere('page_path', 'like', $like);
            });
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }

    public function findVisibleOrFail(TenantUser $viewer, string $conversationId): AiConversation
    {
        $conversation = AiConversation::query()
            ->with(['messages' => static fn ($q) => $q->orderBy('created_at')])
            ->find($conversationId);

        abort_if($conversation === null, 404, __('Conversation not found.'));
        abort_unless($this->canView($viewer, $conversation), 403);

        return $conversation;
    }

    public function canView(TenantUser $viewer, AiConversation $conversation): bool
    {
        if ((string) $conversation->user_id === (string) $viewer->id) {
            return true;
        }

        return $viewer->can('ai_assistant:conversations:audit');
    }

    public function canContinue(TenantUser $viewer, AiConversation $conversation): bool
    {
        return (string) $conversation->user_id === (string) $viewer->id
            && $conversation->status === AssistantConversationStatus::ACTIVE;
    }

    /**
     * @return array<string, mixed>
     */
    public function asListRow(AiConversation $conversation): array
    {
        return [
            'id' => (string) $conversation->id,
            'title' => $conversation->title,
            'module_context' => $conversation->module_context,
            'page_path' => $conversation->page_path,
            'status' => $conversation->status,
            'message_count' => (int) ($conversation->messages_count ?? $conversation->messages()->count()),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
            'user_id' => (string) $conversation->user_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asDetail(AiConversation $conversation): array
    {
        return [
            ...$this->asListRow($conversation),
            'messages' => $conversation->messages
                ->map(fn (AiMessage $message): array => $this->asMessagePayload($message))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asMessagePayload(AiMessage $message): array
    {
        return [
            'id' => (string) $message->id,
            'conversation_id' => (string) $message->conversation_id,
            'role' => $message->role,
            'content' => $message->content,
            'citations' => $message->citations ?? [],
            'model_name' => $message->model_name,
            'prompt_tokens' => $message->prompt_tokens,
            'completion_tokens' => $message->completion_tokens,
            'latency_ms' => $message->latency_ms,
            'status' => $message->status,
            'proposed_action' => $this->pendingProposedActionPayload($message),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * Surface a still-pending proposed action so the confirm card survives a chat reload.
     *
     * @return array<string, mixed>|null
     */
    private function pendingProposedActionPayload(AiMessage $message): ?array
    {
        if ($message->role !== AssistantMessageRole::ASSISTANT) {
            return null;
        }

        $proposal = AiAssistantProposedAction::query()
            ->where('message_id', $message->id)
            ->where('status', AssistantProposedActionStatus::PENDING)
            ->where(static function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('created_at')
            ->first();

        if ($proposal === null) {
            return null;
        }

        return app(AssistantActionService::class)->asApiPayload($proposal);
    }

    /**
     * @param  Builder<AiConversation>  $query
     */
    private function scopeVisibleTo(Builder $query, TenantUser $viewer): void
    {
        if ($viewer->can('ai_assistant:conversations:audit')) {
            return;
        }

        $query->where('user_id', $viewer->id);
    }

    /**
     * @param  list<array<string, mixed>>|null  $citations
     */
    private function storeMessage(
        AiConversation $conversation,
        string $role,
        string $content,
        string $status,
        ?array $citations = null,
        ?string $modelName = null,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        ?int $latencyMs = null,
    ): AiMessage {
        return AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'citations' => $citations,
            'model_name' => $modelName,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'latency_ms' => $latencyMs,
            'status' => $status,
        ]);
    }

    private function titleFromQuestion(string $question): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $question) ?? $question);

        return Str::limit($normalized, 120, '…');
    }
}
