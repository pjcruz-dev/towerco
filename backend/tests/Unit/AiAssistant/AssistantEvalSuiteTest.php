<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\DTOs\LlmPrompt;
use App\Modules\AiAssistant\DTOs\RetrievedKnowledgeChunk;
use App\Modules\AiAssistant\DTOs\ToolCallRequest;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRouter;
use App\Modules\AiAssistant\Support\AssistantChunkRanker;
use App\Modules\AiAssistant\Support\AssistantModuleSuggestionCatalog;
use App\Modules\AiAssistant\Support\AssistantRelevanceGate;
use App\Modules\AiAssistant\Support\LocalGroundedLlmProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lightweight eval suite for real user phrasings (router + ranker + local answer shaping).
 * Keep expanding this list as feedback gaps are triaged.
 */
final class AssistantEvalSuiteTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function intentCases(): array
    {
        return [
            ['How do I create an E-Approval request?', 'submit'],
            ['How to submit a request using Document Approval?', 'submit'],
            ['Where do I track my Document Approval submission?', 'track'],
            ['What if my submission was returned for revision?', 'returned'],
            ['What if my Document Control form is not listed?', 'form_missing'],
            ['How do I approve a request waiting for me?', 'approve'],
        ];
    }

    #[DataProvider('intentCases')]
    public function test_intent_detection(string $question, string $expectedIntent): void
    {
        $this->assertSame($expectedIntent, (new AssistantChunkRanker)->detectIntent($question));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function toolRoutingCases(): array
    {
        return [
            ['What is the status of TKT-00004?', 'get_ticket_by_number'],
            ['What is the status of GEN-F-00042?', 'get_eapproval_submission_by_document_no'],
            ['What is the status of ATC-F-HR-003?', 'get_controlled_document_by_code'],
            ['Look up site code PH-CEB-042', 'get_site_by_code'],
            ['How many Pending Request I have?', 'list_my_eapproval_submissions'],
            ['What are my pending approvals?', 'list_my_pending_approvals'],
        ];
    }

    #[DataProvider('toolRoutingCases')]
    public function test_tool_routing(string $question, string $expectedTool): void
    {
        config(['ai_assistant.tools.enabled' => true]);
        $plan = app(AssistantToolRouter::class)->plan($question);

        $this->assertTrue($plan->useTools(), 'Expected tools for: '.$question);
        $this->assertTrue(
            collect($plan->calls)->contains(
                fn (ToolCallRequest $call): bool => $call->tool === $expectedTool,
            ),
            'Expected tool '.$expectedTool.' for: '.$question,
        );
    }

    public function test_module_bias_prefers_matching_module_chunk(): void
    {
        $ticketing = $this->chunk('ticketing-basics', 'Ticketing basics', 'ticketing', 0.3);
        $sites = $this->chunk('sites-overview', 'Sites overview', 'sites', 0.35);

        $ranked = (new AssistantChunkRanker)->rank(
            [$sites, $ticketing],
            'How do I create a ticket?',
            'ticketing',
            '/ticketing',
        );

        $this->assertSame('ticketing-basics', $ranked[0]->slug);
    }

    public function test_module_suggestion_catalog_covers_key_modules(): void
    {
        $catalog = new AssistantModuleSuggestionCatalog;
        foreach (['e_approval', 'ticketing', 'document_register', 'procurement_one', 'core'] as $module) {
            $suggestions = $catalog->forModule($module);
            $this->assertNotEmpty($suggestions);
            $this->assertLessThanOrEqual(3, count($suggestions));
        }
    }

    public function test_off_topic_eval_returns_insufficient_context(): void
    {
        $chunk = $this->chunk(
            'document-register',
            'Document register (controlled documents)',
            'document_register',
            0.5,
            "# Document register\n\nFind controlled documents.",
        );

        $result = (new LocalGroundedLlmProvider)->complete(new LlmPrompt(
            system: 'test',
            user: "USER_QUESTION:\nWhat is the weather in Manila today?\n\nBEGIN_LIVE_SYSTEM_DATA\n(no live)\nEND_LIVE_SYSTEM_DATA",
            chunks: [$chunk],
        ));

        $this->assertTrue($result->insufficientContext);
        $this->assertStringContainsString('TowerOS workspace assistant', $result->answer);
    }

    public function test_relevance_gate_keeps_in_scope_howto(): void
    {
        $gate = new AssistantRelevanceGate;
        $chunk = $this->chunk(
            'e-approval-create-request',
            'Create an E-Approval request',
            'e_approval',
            0.25,
            "# Create an E-Approval request\n\nSubmit forms for review.",
        );

        $this->assertTrue($gate->isRelevant($chunk, 'How do I create an E-Approval request?'));
    }

    private function chunk(
        string $slug,
        string $title,
        string $moduleKey,
        float $score,
        ?string $body = null,
    ): RetrievedKnowledgeChunk {
        $content = $body ?? "# {$title}\n\nHelp for {$moduleKey}.";

        return new RetrievedKnowledgeChunk(
            chunkId: $slug.'-1',
            sourceId: $slug,
            vectorId: $slug.'-v',
            content: $content,
            score: $score,
            scope: 'global',
            moduleKey: $moduleKey,
            title: $title,
            slug: $slug,
            version: 1,
            permissions: [],
            relatedRoutes: ['/'.$moduleKey],
            sourceBody: $content,
        );
    }
}
