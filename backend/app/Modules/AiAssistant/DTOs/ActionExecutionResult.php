<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class ActionExecutionResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $ok,
        public ?string $entityType = null,
        public ?string $entityId = null,
        public ?string $entityLabel = null,
        public array $meta = [],
        public ?string $error = null,
        public ?string $href = null,
    ) {}
}
