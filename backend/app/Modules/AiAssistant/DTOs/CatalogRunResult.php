<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class CatalogRunResult
{
    /**
     * @param  array<string, mixed>|null  $proposedAction
     * @param  list<ToolResult>  $toolResults
     */
    public function __construct(
        public ?array $proposedAction,
        public array $toolResults,
        public ?string $executedSummary = null,
    ) {}
}
