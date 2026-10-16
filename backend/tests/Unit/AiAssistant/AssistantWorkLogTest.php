<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AiAssistant\Support\AssistantWorkLog;
use Tests\TestCase;

final class AssistantWorkLogTest extends TestCase
{
    public function test_steps_list_search_tools_model_and_review(): void
    {
        $steps = AssistantWorkLog::build(
            searched: true,
            searchFailed: false,
            chunkTitles: ['How to submit a request'],
            toolResults: [
                new ToolResult(
                    tool: 'list_my_open_tickets',
                    ok: true,
                    summary: '2 open tickets.',
                    rowCount: 2,
                ),
            ],
            modelName: 'grok-4.7',
            latencyMs: 4200,
            modelFailed: false,
            proposedAction: ['summary' => 'Draft a ticket.'],
        );

        $this->assertSame(
            ['search', 'tool', 'model', 'action'],
            array_column($steps, 'kind'),
        );
        $this->assertSame('Searched approved knowledge', $steps[0]['title']);
        $this->assertStringContainsString('How to submit a request', (string) $steps[0]['detail']);
        $this->assertSame('Ran List my open tickets', $steps[1]['title']);
        $this->assertSame('2 rows. 2 open tickets.', $steps[1]['detail']);
        $this->assertSame('done', $steps[1]['status']);
        $this->assertSame('Asked grok-4.7', $steps[2]['title']);
        $this->assertSame('Finished in 4.2s', $steps[2]['detail']);
        $this->assertSame('Prepared an action for review', $steps[3]['title']);
        $this->assertStringContainsString('Nothing has been saved yet.', (string) $steps[3]['detail']);
    }

    public function test_failed_tool_and_model_are_marked_failed(): void
    {
        $steps = AssistantWorkLog::build(
            searched: false,
            searchFailed: false,
            chunkTitles: [],
            toolResults: [
                new ToolResult(tool: 'get_ticket_by_number', ok: false, error: 'Ticket not found.'),
            ],
            modelName: 'composer-2.5',
            latencyMs: null,
            modelFailed: true,
        );

        $this->assertSame('failed', $steps[0]['status']);
        $this->assertSame('Ticket not found.', $steps[0]['detail']);
        $this->assertSame('failed', $steps[1]['status']);
        $this->assertSame('Model request failed', $steps[1]['title']);
    }
}
