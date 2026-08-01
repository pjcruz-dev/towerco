<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Contracts;

use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\Identity\Models\TenantUser;

/**
 * Allowlisted controlled write action. Propose never mutates; execute runs only after confirm.
 */
interface AssistantActionInterface
{
    public function name(): string;

    public function description(): string;

    public function requiredModule(): ?string;

    /**
     * Domain permissions required to propose and to execute (in addition to assistant action perms).
     *
     * @return list<string>
     */
    public function requiredDomainPermissions(): array;

    /**
     * @return array<string, mixed>
     */
    public function argumentRules(): array;

    /**
     * Build a draft proposal from the user question / extracted args. Must NOT mutate domain data.
     *
     * @param  array<string, mixed>  $args
     */
    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft;

    /**
     * Execute a previously proposed (and re-validated) payload via normal service layer.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult;
}
