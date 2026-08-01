<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

/**
 * Classification of how to answer: docs, tools, or both.
 */
final readonly class ToolPlan
{
    public const MODE_DOCS = 'docs';

    public const MODE_TOOLS = 'tools';

    public const MODE_BOTH = 'both';

    /**
     * @param  list<ToolCallRequest>  $calls
     */
    public function __construct(
        public string $mode,
        public array $calls = [],
    ) {}

    public function useDocs(): bool
    {
        return $this->mode === self::MODE_DOCS || $this->mode === self::MODE_BOTH;
    }

    public function useTools(): bool
    {
        return ($this->mode === self::MODE_TOOLS || $this->mode === self::MODE_BOTH)
            && $this->calls !== [];
    }
}
