<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class AskAssistantInput
{
    public function __construct(
        public string $question,
        public ?string $conversationId = null,
        public ?string $moduleContext = null,
        public ?string $pagePath = null,
    ) {}

    /**
     * @param  array{
     *   question: string,
     *   conversation_id?: string|null,
     *   module_context?: string|null,
     *   page_path?: string|null
     * }  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            question: trim($validated['question']),
            conversationId: isset($validated['conversation_id']) && is_string($validated['conversation_id'])
                ? $validated['conversation_id']
                : null,
            moduleContext: isset($validated['module_context']) && is_string($validated['module_context'])
                ? trim($validated['module_context'])
                : null,
            pagePath: isset($validated['page_path']) && is_string($validated['page_path'])
                ? trim($validated['page_path'])
                : null,
        );
    }
}
