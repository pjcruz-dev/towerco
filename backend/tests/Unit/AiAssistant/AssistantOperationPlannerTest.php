<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Services\AssistantCatalogRunner;
use App\Modules\AiAssistant\Services\AssistantOperationPlanner;
use Tests\TestCase;

final class AssistantOperationPlannerTest extends TestCase
{
    public function test_parser_keeps_a_catalog_write_and_drops_unknown_names(): void
    {
        $planner = app(AssistantOperationPlanner::class);
        $catalog = [[
            'kind' => 'write',
            'name' => 'reject_eapproval_submission',
            'description' => 'Reject a pending submission.',
            'module' => 'e_approval',
            'arguments' => [
                ['name' => 'document_no', 'required' => true],
                ['name' => 'remarks', 'required' => false],
            ],
        ]];

        $accepted = $planner->parse(<<<'JSON'
```json
{"decision":"write","name":"reject_eapproval_submission","arguments":{"document_no":"ATC-DOC-EAPPROVAL--X-004","remarks":"Not approved","sql":"drop"}}
```
JSON, $catalog);

        $this->assertSame('write', $accepted->decision);
        $this->assertSame('reject_eapproval_submission', $accepted->name);
        $this->assertSame('ATC-DOC-EAPPROVAL--X-004', $accepted->arguments['document_no'] ?? null);
        $this->assertSame('Not approved', $accepted->arguments['remarks'] ?? null);
        $this->assertArrayNotHasKey('sql', $accepted->arguments);

        $rejected = $planner->parse(
            '{"decision":"write","name":"delete_tenant","arguments":{}}',
            $catalog,
        );
        $this->assertSame('answer', $rejected->decision);
        $this->assertNull($rejected->name);

        $prose = $planner->parse('I cannot do that from here.', $catalog);
        $this->assertSame('answer', $prose->decision);
    }

    public function test_parser_rejects_a_read_name_used_as_a_write(): void
    {
        $planner = app(AssistantOperationPlanner::class);
        $catalog = [[
            'kind' => 'read',
            'name' => 'find_it_asset',
            'description' => 'Find an asset.',
            'module' => 'asset_management',
            'arguments' => [
                ['name' => 'query', 'required' => true],
            ],
        ]];

        $decision = $planner->parse(
            '{"decision":"write","name":"find_it_asset","arguments":{"query":"SN-1"}}',
            $catalog,
        );

        $this->assertSame('answer', $decision->decision);
    }

    public function test_execute_sql_is_accepted_when_the_model_marks_it_as_a_write(): void
    {
        $planner = app(AssistantOperationPlanner::class);
        $catalog = [[
            'kind' => 'read',
            'name' => 'execute_sql',
            'description' => 'Run one statement.',
            'module' => null,
            'arguments' => [
                ['name' => 'sql', 'required' => true],
            ],
        ]];

        $decision = $planner->parse(
            '{"decision":"write","name":"execute_sql","arguments":{"sql":"UPDATE it_assets SET status = \'LOST\' WHERE asset_tag = \'ATC-LAP-000001\'"}}',
            $catalog,
        );

        $this->assertSame('read', $decision->decision);
        $this->assertSame('execute_sql', $decision->name);
        $this->assertStringContainsString('UPDATE it_assets', (string) ($decision->arguments['sql'] ?? ''));
    }

    public function test_parser_keeps_the_purpose_and_note_for_the_timeline(): void
    {
        $planner = app(AssistantOperationPlanner::class);
        $catalog = [[
            'kind' => 'read',
            'name' => 'execute_sql',
            'description' => 'Run one statement.',
            'module' => null,
            'arguments' => [
                ['name' => 'sql', 'required' => true],
            ],
        ]];

        $decision = $planner->parse(
            '{"decision":"read","name":"execute_sql","purpose":"Count active users in Procurement roles","note":"We have two procurement roles. I will count the active users for each.","arguments":{"sql":"SELECT 1"}}',
            $catalog,
        );

        $this->assertSame('Count active users in Procurement roles', $decision->purpose);
        $this->assertSame('We have two procurement roles. I will count the active users for each.', $decision->note);
    }

    public function test_cancel_wording_cannot_open_a_reject_card(): void
    {
        $runner = app(AssistantCatalogRunner::class);

        $this->assertFalse($runner->writeMatchesQuestion(
            'ATC-DOC-EAPPROVAL--X-008 Cancel this request',
            'reject_eapproval_submission',
        ));
        $this->assertTrue($runner->writeMatchesQuestion(
            'ATC-DOC-EAPPROVAL--X-008 Cancel this request',
            'cancel_eapproval_submission',
        ));
        $this->assertTrue($runner->writeMatchesQuestion(
            'ATC-DOC-EAPPROVAL--X-004 Can you reject the request?',
            'reject_eapproval_submission',
        ));
    }
}
