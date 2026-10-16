<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ConversationTurn;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AiAssistant\Services\Tools\AssistantToolFallbackPlanner;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRegistry;
use App\Modules\AiAssistant\Support\AssistantPromptBuilder;
use App\Modules\AiAssistant\Support\PromptSecurityService;
use App\Modules\Identity\Models\TenantUser;
use Tests\TestCase;

final class AssistantPhase2IntelligenceTest extends TestCase
{
    public function test_fallback_planner_resolves_ticket_follow_up_from_history(): void
    {
        $registry = new AssistantToolRegistry([
            $this->stubTool('get_ticket_by_number'),
        ]);
        $planner = new AssistantToolFallbackPlanner($registry);

        $plan = $planner->plan('What is its status?', 'ticketing', [
            new ConversationTurn('user', 'What is the status of TKT-00004?'),
            new ConversationTurn('assistant', 'Ticket TKT-00004 — Access request. Status: open.'),
        ]);

        $this->assertNotNull($plan);
        $this->assertTrue($plan->useTools());
        $this->assertSame('get_ticket_by_number', $plan->calls[0]->tool);
        $this->assertSame('TKT-00004', $plan->calls[0]->args['ticket_number'] ?? null);
    }

    public function test_fallback_planner_uses_module_context_for_eapproval_list(): void
    {
        $registry = new AssistantToolRegistry([
            $this->stubTool('list_my_eapproval_submissions'),
        ]);
        $planner = new AssistantToolFallbackPlanner($registry);

        $plan = $planner->plan('How many pending ones do I have?', 'e_approval', []);

        $this->assertNotNull($plan);
        $this->assertSame('list_my_eapproval_submissions', $plan->calls[0]->tool);
        $this->assertSame('pending', $plan->calls[0]->args['status'] ?? null);
    }

    public function test_prompt_builder_includes_conversation_history(): void
    {
        $builder = new AssistantPromptBuilder(new PromptSecurityService);
        $prompt = $builder->build(
            question: 'What about that ticket?',
            chunks: [],
            history: [
                new ConversationTurn('user', 'Status of TKT-00004?'),
                new ConversationTurn('assistant', 'Ticket TKT-00004 is open.'),
            ],
        );

        $this->assertStringContainsString('BEGIN_CONVERSATION_HISTORY', $prompt->user);
        $this->assertStringContainsString('TKT-00004', $prompt->user);
        $this->assertStringContainsString('USER_QUESTION:', $prompt->user);
        $this->assertStringContainsString('What about that ticket?', $prompt->user);
        $this->assertStringContainsString('in-product help assistant', $prompt->system);
    }

    public function test_admin_builder_prompt_is_for_tenant_administrators(): void
    {
        $builder = new AssistantPromptBuilder(new PromptSecurityService);
        $prompt = $builder->build(
            question: 'Change the asset status labels.',
            chunks: [],
            adminBuilder: true,
        );

        $this->assertStringContainsString('builder for a tenant administrator', $prompt->system);
        $this->assertStringContainsString('Just do the operation', $prompt->system);
        $this->assertStringNotContainsString('confirm the card', $prompt->system);
        $this->assertStringNotContainsString('in-product help assistant', $prompt->system);
    }

    private function stubTool(string $name): AssistantToolInterface
    {
        return new class($name) implements AssistantToolInterface
        {
            public function __construct(private readonly string $toolName) {}

            public function name(): string
            {
                return $this->toolName;
            }

            public function description(): string
            {
                return 'stub';
            }

            public function requiredModule(): ?string
            {
                return null;
            }

            public function requiredPermissions(): array
            {
                return [];
            }

            public function argumentRules(): array
            {
                return [];
            }

            public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
            {
                return new ToolResult(tool: $this->toolName, ok: true);
            }
        };
    }
}
