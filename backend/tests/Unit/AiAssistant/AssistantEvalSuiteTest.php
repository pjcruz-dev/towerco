<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\DTOs\ToolCallRequest;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRouter;
use App\Modules\AiAssistant\Support\AssistantModuleSuggestionCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AssistantEvalSuiteTest extends TestCase
{
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

    public function test_module_suggestion_catalog_covers_key_modules(): void
    {
        $catalog = new AssistantModuleSuggestionCatalog;
        foreach (['e_approval', 'ticketing', 'document_register', 'core'] as $module) {
            $suggestions = $catalog->forModule($module);
            $this->assertNotEmpty($suggestions);
            $this->assertLessThanOrEqual(3, count($suggestions));
        }
    }
}
