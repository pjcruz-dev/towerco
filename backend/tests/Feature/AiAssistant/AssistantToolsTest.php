<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\DTOs\ToolCallRequest;
use App\Modules\AiAssistant\Services\Tools\AssistantToolExecutor;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRouter;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\Workspace\Models\TenantActivityLog;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantToolsTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'ai_assistant',
                'e_approval',
                'sites',
                'ticketing',
                'documents',
                'document_register',
            ],
            'ai_assistant.enabled' => true,
            'ai_assistant.embedding_provider' => 'local',
            'ai_assistant.vector_store' => 'database',
            'ai_assistant.llm_provider' => 'local',
            'ai_assistant.tools.enabled' => true,
            'ai_assistant.tools.max_per_request' => 2,
            'ai_assistant.tools.max_rows' => 10,
            'queue.default' => 'sync',
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_router_selects_pending_approvals_tool(): void
    {
        $plan = app(AssistantToolRouter::class)->plan('What are my pending approvals?');

        $this->assertTrue($plan->useTools());
        $this->assertSame('list_my_pending_approvals', $plan->calls[0]->tool);
    }

    public function test_router_selects_my_submissions_tool_for_pending_request_count(): void
    {
        $plan = app(AssistantToolRouter::class)->plan('How many Pending Request I have?');

        $this->assertTrue($plan->useTools());
        $this->assertSame('tools', $plan->mode);
        $this->assertTrue(
            collect($plan->calls)->contains(
                fn (ToolCallRequest $call): bool => $call->tool === 'list_my_eapproval_submissions'
                    && ($call->args['status'] ?? null) === 'pending',
            ),
        );
    }

    public function test_router_selects_my_submissions_tool_for_approved_request_count(): void
    {
        $plan = app(AssistantToolRouter::class)->plan('how many approved request i have?');

        $this->assertTrue($plan->useTools());
        $this->assertTrue(
            collect($plan->calls)->contains(
                fn (ToolCallRequest $call): bool => $call->tool === 'list_my_eapproval_submissions'
                    && ($call->args['status'] ?? null) === 'approved',
            ),
        );
    }

    public function test_router_selects_controlled_document_tool_for_document_code_status(): void
    {
        $plan = app(AssistantToolRouter::class)->plan('What is the status of ATC-F-HR-003?');

        $this->assertTrue($plan->useTools());
        $this->assertSame('tools', $plan->mode);
        $this->assertTrue(
            collect($plan->calls)->contains(
                fn (ToolCallRequest $call): bool => $call->tool === 'get_controlled_document_by_code'
                    && ($call->args['document_code'] ?? null) === 'ATC-F-HR-003',
            ),
        );
    }

    public function test_router_selects_submission_tool_for_eapproval_document_no(): void
    {
        $plan = app(AssistantToolRouter::class)->plan('What is the status of GEN-F-00042?');

        $this->assertTrue($plan->useTools());
        $this->assertTrue(
            collect($plan->calls)->contains(
                fn (ToolCallRequest $call): bool => $call->tool === 'get_eapproval_submission_by_document_no'
                    && ($call->args['document_no'] ?? null) === 'GEN-F-00042',
            ),
        );
        $this->assertFalse(
            collect($plan->calls)->contains(
                fn (ToolCallRequest $call): bool => $call->tool === 'get_controlled_document_by_code',
            ),
        );
    }

    public function test_router_selects_ticket_tool_for_tkt_status_not_document_register(): void
    {
        $plan = app(AssistantToolRouter::class)->plan('What is the status of TKT-00004?');

        $this->assertTrue($plan->useTools());
        $this->assertTrue(
            collect($plan->calls)->contains(
                fn (ToolCallRequest $call): bool => $call->tool === 'get_ticket_by_number'
                    && ($call->args['ticket_number'] ?? null) === 'TKT-00004',
            ),
        );
        $this->assertFalse(
            collect($plan->calls)->contains(
                fn (ToolCallRequest $call): bool => $call->tool === 'get_controlled_document_by_code',
            ),
        );
    }

    public function test_get_controlled_document_by_code_returns_live_status(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        \App\Modules\Documents\Models\ControlledDocument::query()->create([
            'document_code' => 'ATC-F-HR-003',
            'title' => 'HR Policy',
            'document_type' => 'P',
            'department' => 'HR',
            'current_revision' => 1,
            'status' => 'published',
        ]);

        $result = app(AssistantToolExecutor::class)->executeOne(
            $this->testTenantAdmin,
            new ToolCallRequest('get_controlled_document_by_code', ['document_code' => 'ATC-F-HR-003']),
        );

        $this->assertTrue($result->ok);
        $this->assertSame(1, $result->rowCount);
        $this->assertStringContainsString('ATC-F-HR-003', $result->summary);
        $this->assertStringContainsString('Status: published', $result->summary);
        $this->assertSame('published', $result->data['document']['status'] ?? null);

        tenancy()->end();
    }

    public function test_get_eapproval_submission_by_document_no_returns_live_status(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $form = \App\Modules\EApproval\Models\EApprovalForm::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Cash Advance',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);

        $submission = \App\Modules\EApproval\Models\EApprovalSubmission::query()->create([
            'document_no' => 'GEN-F-00042',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
            'current_step' => 1,
        ]);

        $result = app(AssistantToolExecutor::class)->executeOne(
            $this->testTenantAdmin,
            new ToolCallRequest('get_eapproval_submission_by_document_no', ['document_no' => 'GEN-F-00042']),
        );

        $this->assertTrue($result->ok);
        $this->assertSame(1, $result->rowCount);
        $this->assertStringContainsString('GEN-F-00042', $result->summary);
        $this->assertStringContainsString('Status: pending', $result->summary);
        $this->assertSame((string) $submission->id, $result->data['submission']['id'] ?? null);

        tenancy()->end();
    }

    public function test_get_ticket_by_number_returns_live_status(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $ticket = \App\Models\TicketingTicket::query()->create([
            'ticket_number' => 4,
            'title' => 'Access request',
            'description' => 'Need access',
            'status' => \App\Models\TicketingTicket::STATUS_OPEN,
            'priority' => \App\Models\TicketingTicket::PRIORITY_NORMAL,
            'requester_id' => $this->testTenantAdmin->id,
        ]);

        $result = app(AssistantToolExecutor::class)->executeOne(
            $this->testTenantAdmin,
            new ToolCallRequest('get_ticket_by_number', ['ticket_number' => 'TKT-00004']),
        );

        $this->assertTrue($result->ok);
        $this->assertSame(1, $result->rowCount);
        $this->assertStringContainsString('TKT-00004', $result->summary);
        $this->assertStringContainsString('Status: open', $result->summary);
        $this->assertSame((string) $ticket->id, $result->data['ticket']['id'] ?? null);

        tenancy()->end();
    }

    public function test_tool_permission_denial_is_audited_and_does_not_leak_data(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $viewer = TenantUser::query()->create([
            'name' => 'No Approve',
            'email' => 'no-approve@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('viewer');
        $viewer->givePermissionTo('ai_assistant:use');
        // Intentionally no e_approval:approve

        $result = app(AssistantToolExecutor::class)->executeOne(
            $viewer,
            new ToolCallRequest('list_my_pending_approvals'),
        );

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Missing permission', (string) $result->error);
        $this->assertSame([], $result->data);

        $this->assertTrue(
            TenantActivityLog::query()
                ->where('action', 'assistant.tool.invoke')
                ->where('entity_id', 'list_my_pending_approvals')
                ->exists(),
        );

        tenancy()->end();
    }

    public function test_get_site_by_code_returns_live_data_when_permitted(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        Site::query()->create([
            'site_code' => 'PH-NCR-001',
            'name' => 'Manila Hub',
            'type' => 'tower',
            'status' => 'active',
            'latitude' => 14.5,
            'longitude' => 121.0,
        ]);

        $result = app(AssistantToolExecutor::class)->executeOne(
            $this->testTenantAdmin,
            new ToolCallRequest('get_site_by_code', ['site_code' => 'PH-NCR-001']),
        );

        $this->assertTrue($result->ok);
        $this->assertSame(1, $result->rowCount);
        $this->assertSame('PH-NCR-001', $result->data['site']['site_code'] ?? null);
        $this->assertSame('live_data', $result->toCitationArray()['type']);

        tenancy()->end();
    }

    public function test_ask_api_uses_live_data_for_operational_question(): void
    {
        tenancy()->initialize($this->testTenant);
        Site::query()->create([
            'site_code' => 'PH-CEB-042',
            'name' => 'Cebu North',
            'type' => 'tower',
            'status' => 'active',
            'latitude' => 10.3,
            'longitude' => 123.9,
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'Look up site code PH-CEB-042',
                'module_context' => 'sites',
                'page_path' => '/sites',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.used_live_data', true);

        $citations = $response->json('data.citations');
        $this->assertIsArray($citations);
        $this->assertTrue(
            collect($citations)->contains(fn ($c) => ($c['type'] ?? null) === 'live_data'),
        );
        $this->assertStringContainsStringIgnoringCase('Cebu', (string) $response->json('data.answer'));
    }

    public function test_ask_api_returns_live_document_status_for_document_code(): void
    {
        tenancy()->initialize($this->testTenant);
        \App\Modules\Documents\Models\ControlledDocument::query()->create([
            'document_code' => 'ATC-F-HR-003',
            'title' => 'HR Policy',
            'document_type' => 'P',
            'department' => 'HR',
            'current_revision' => 1,
            'status' => 'published',
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'What is the status of ATC-F-HR-003?',
                'module_context' => 'document_register',
                'page_path' => '/documents/controlled',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.used_live_data', true);

        $answer = (string) $response->json('data.answer');
        $this->assertStringContainsString('ATC-F-HR-003', $answer);
        $this->assertStringContainsString('published', $answer);
        $this->assertStringNotContainsString('Open **Document register**', $answer);
    }

    public function test_ask_denies_tool_data_in_answer_when_user_lacks_permission(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $viewer = TenantUser::query()->create([
            'name' => 'Sites Blind',
            'email' => 'sites-blind@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('viewer');
        $viewer->givePermissionTo('ai_assistant:use');
        // No sites:view

        Site::query()->create([
            'site_code' => 'SECRET-001',
            'name' => 'Secret Site',
            'type' => 'tower',
            'status' => 'active',
            'latitude' => 1.0,
            'longitude' => 1.0,
        ]);
        tenancy()->end();

        $response = $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'Look up site code SECRET-001',
            ]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('data.used_live_data'));
        $this->assertStringNotContainsStringIgnoringCase('Secret Site', (string) $response->json('data.answer'));
    }

    public function test_search_workspace_entities_returns_forms_and_submission_operational_fields(): void
    {
        config([
            'toweros.notifications_mail_mailer' => 'array',
            'mail.default' => 'array',
            'queue.default' => 'sync',
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $form = \App\Modules\EApproval\Models\EApprovalForm::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'AI Search Parity Form',
            'description' => 'Published form for assistant search parity',
            'category' => 'Ops',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
            'accepts_new_submissions' => true,
        ]);

        \App\Modules\EApproval\Models\EApprovalSubmission::query()->create([
            'document_no' => 'GEN-F-AISEARCH',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
            'current_step' => 2,
            'approval_cycle' => 1,
        ]);

        $result = app(AssistantToolExecutor::class)->executeOne(
            $this->testTenantAdmin,
            new ToolCallRequest('search_workspace_entities', ['query' => 'AI Search Parity']),
        );

        $this->assertTrue($result->ok);
        $rows = collect($result->data['results'] ?? []);
        $this->assertTrue($rows->contains(
            static fn (array $row): bool => ($row['entity_type'] ?? null) === 'form'
                && ($row['id'] ?? null) === (string) $form->id
                && ($row['status_label'] ?? null) === 'Published',
        ));

        $submissionHit = $rows->first(
            static fn (array $row): bool => ($row['entity_type'] ?? null) === 'submission'
                && ($row['title'] ?? null) === 'GEN-F-AISEARCH',
        );
        $this->assertIsArray($submissionHit);
        $this->assertSame('pending', $submissionHit['status'] ?? null);
        $this->assertSame('Pending', $submissionHit['status_label'] ?? null);
        $this->assertSame(2, $submissionHit['current_step'] ?? null);

        tenancy()->end();
    }
}
