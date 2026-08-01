<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class AskAssistantResult
{
    /**
     * @param  list<array<string, mixed>>  $citations
     * @param  list<string>  $suggestedFollowups
     * @param  list<array{label: string, href: string}>  $relatedLinks
     * @param  array<string, mixed>|null  $proposedAction
     */
    public function __construct(
        public string $conversationId,
        public string $messageId,
        public string $answer,
        public array $citations,
        public string $status,
        public array $suggestedFollowups = [],
        public array $relatedLinks = [],
        public ?string $modelName = null,
        public bool $usedLiveData = false,
        public ?array $proposedAction = null,
        public ?string $errorCode = null,
        public ?array $providerNotice = null,
    ) {}

    /**
     * @return array{
     *   conversation_id: string,
     *   message_id: string,
     *   answer: string,
     *   citations: list<array<string, mixed>>,
     *   suggested_followups: list<string>,
     *   related_links: list<array{label: string, href: string}>,
     *   status: string,
     *   model_name: string|null,
     *   used_live_data: bool,
     *   proposed_action: array<string, mixed>|null,
     *   error_code: string|null,
     *   provider_notice: array<string, mixed>|null
     * }
     */
    public function toArray(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'answer' => $this->answer,
            'citations' => $this->citations,
            'suggested_followups' => $this->suggestedFollowups,
            'related_links' => $this->relatedLinks,
            'status' => $this->status,
            'model_name' => $this->modelName,
            'used_live_data' => $this->usedLiveData,
            'proposed_action' => $this->proposedAction,
            'error_code' => $this->errorCode,
            'provider_notice' => $this->providerNotice,
        ];
    }
}
