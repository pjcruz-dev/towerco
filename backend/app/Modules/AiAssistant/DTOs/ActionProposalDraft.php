<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class ActionProposalDraft
{
    /**
     * @param  array<string, mixed>  $payload  Validated args that will be executed on confirm
     * @param  array<string, mixed>  $preview  Safe UI preview fields
     * @param  list<array{key: string, label: string, type: string, required?: bool}>  $editableFields
     */
    public function __construct(
        public string $action,
        public string $title,
        public string $summary,
        public array $payload,
        public array $preview = [],
        public array $editableFields = [],
        public ?string $moduleKey = null,
        public ?string $confirmLabel = 'Confirm & create',
    ) {}
}
