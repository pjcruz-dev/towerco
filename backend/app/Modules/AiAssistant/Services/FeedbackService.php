<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Models\AiAssistantFeedback;
use App\Modules\AiAssistant\Models\AiConversation;
use App\Modules\AiAssistant\Models\AiMessage;
use App\Modules\AiAssistant\Support\AssistantFeedbackRating;
use App\Modules\AiAssistant\Support\AssistantMessageRole;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;

final class FeedbackService
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly TenantActivityLogger $activity,
    ) {}

    /**
     * @param  array{message_id: string, rating: string, comment?: string|null}  $payload
     * @return array<string, mixed>
     */
    public function submit(TenantUser $viewer, array $payload): array
    {
        $message = AiMessage::query()->find($payload['message_id']);
        abort_if($message === null, 404, __('Message not found.'));
        abort_unless($message->role === AssistantMessageRole::ASSISTANT, 422, __('Feedback is only allowed on assistant messages.'));

        $conversation = AiConversation::query()->find($message->conversation_id);
        abort_if($conversation === null, 404, __('Conversation not found.'));
        abort_unless($this->conversations->canView($viewer, $conversation), 403);
        abort_unless((string) $conversation->user_id === (string) $viewer->id, 403);

        abort_unless(in_array($payload['rating'], AssistantFeedbackRating::values(), true), 422);

        $feedback = AiAssistantFeedback::query()->updateOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => $viewer->id,
            ],
            [
                'conversation_id' => $conversation->id,
                'rating' => $payload['rating'],
                'comment' => isset($payload['comment']) ? trim((string) $payload['comment']) : null,
            ],
        );

        $this->activity->record(
            module: 'ai_assistant',
            action: 'assistant.feedback',
            summary: 'Assistant feedback '.$feedback->rating,
            entityType: 'ai_message',
            entityId: (string) $message->id,
            entityLabel: $conversation->title,
            actor: $viewer,
            metadata: [
                'conversation_id' => (string) $conversation->id,
                'feedback_id' => (string) $feedback->id,
                'rating' => $feedback->rating,
            ],
        );

        return [
            'id' => (string) $feedback->id,
            'conversation_id' => (string) $feedback->conversation_id,
            'message_id' => (string) $feedback->message_id,
            'rating' => $feedback->rating,
            'comment' => $feedback->comment,
            'created_at' => $feedback->created_at?->toIso8601String(),
            'updated_at' => $feedback->updated_at?->toIso8601String(),
        ];
    }
}
