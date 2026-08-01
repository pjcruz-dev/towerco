<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Contracts;

use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\Identity\Models\TenantUser;

/**
 * Allowlisted read-only assistant tool.
 * Implementations must call existing module services and never run LLM-supplied SQL.
 */
interface AssistantToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * Module key that must be enabled for the current tenant (null = always available).
     */
    public function requiredModule(): ?string;

    /**
     * All of these permissions are required.
     *
     * @return list<string>
     */
    public function requiredPermissions(): array;

    /**
     * @return array<string, mixed> Laravel-style validation rules for tool args
     */
    public function argumentRules(): array;

    /**
     * @param  array<string, mixed>  $args  Validated args
     */
    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult;
}
