<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ConversationTurn;
use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AiAssistant\Services\Tools\AssistantToolFallbackPlanner;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRegistry;
use App\Modules\AiAssistant\Support\AssistantPromptBuilder;
use App\Modules\AiAssistant\Support\AssistantRelevanceGate;
use App\Modules\AiAssistant\Support\PromptSecurityService;
use App\Modules\Identity\Models\TenantUser;
use Tests\TestCase;

final class AssistantPhase2IntelligenceTest extends TestCase
{
    public function test_relevance_gate_rejects_weak_unrelated_chunk(): void
    {
        $gate = new AssistantRelevanceGate;
        $chunk = new RetrievedKnowledgeChunk(
            chunkId: '1',
            sourceId: 'document-register',
            vectorId: 'v1',
            content: 'Document register overview',
            score: 0.08,
            scope: 'global',
            moduleKey: 'document_register',
            title: 'Document register (controlled documents)',
            slug: 'document-register',
            version: 1,
            permissions: [],
            relatedRoutes: [],
            sourceBody: "# Document register\n\nFind controlled documents.",
        );

        $this->assertSame([], $gate->filter([$chunk], 'Write me a poem about cats and the weather'));
    }

    public function test_relevance_gate_rejects_high_score_without_lexical_overlap(): void
    {
        $gate = new AssistantRelevanceGate;
        $chunk = new RetrievedKnowledgeChunk(
            chunkId: '1',
            sourceId: 'document-register',
            vectorId: 'v1',
            content: 'Document register overview',
            score: 0.45,
            scope: 'global',
            moduleKey: 'document_register',
            title: 'Document register (controlled documents)',
            slug: 'document-register',
            version: 1,
            permissions: [],
            relatedRoutes: [],
            sourceBody: "# Document register\n\nFind controlled documents.",
        );

        $this->assertFalse($gate->isRelevant($chunk, 'Write me a poem about cats'));
    }

    public function test_relevance_gate_keeps_lexically_matching_chunk(): void
    {
        $gate = new AssistantRelevanceGate;
        $chunk = new RetrievedKnowledgeChunk(
            chunkId: '1',
            sourceId: 'e-approval-create-request',
            vectorId: 'v1',
            content: 'Create an E-Approval request',
            score: 0.2,
            scope: 'global',
            moduleKey: 'e_approval',
            title: 'Create an E-Approval request',
            slug: 'e-approval-create-request',
            version: 1,
            permissions: [],
            relatedRoutes: [],
            sourceBody: "# Create an E-Approval request\n\nSubmit forms for review.",
        );

        $kept = $gate->filter([$chunk], 'How do I create an E-Approval request?');
        $this->assertCount(1, $kept);
    }

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
