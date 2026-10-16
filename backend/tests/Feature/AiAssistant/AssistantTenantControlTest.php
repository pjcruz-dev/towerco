<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Models\TicketingTicket;
use App\Modules\AiAssistant\Services\Actions\AssistantActionRouter;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\AiAssistant\BindsDeterministicAssistantLlm;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantTenantControlTest extends TestCase
{
    use BindsDeterministicAssistantLlm;
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
                'asset_management',
                'ticketing',
                'document_register',
            ],
            'ai_assistant.enabled' => true,
            'ai_assistant.embedding_provider' => 'local',
            'ai_assistant.vector_store' => 'database',
            'ai_assistant.llm_provider' => 'cursor',
            'ai_assistant.tools.enabled' => true,
            'ai_assistant.actions.enabled' => true,
        ]);

        $this->bindDeterministicAssistantLlm();
        $this->bootInMemoryTenantApi();
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_router_names_the_new_tenant_saves(): void
    {
        $router = app(AssistantActionRouter::class);

        $this->assertSame('create_tenant_user', $router->match('create user jane.viewer@test.localhost named Jane Viewer password TempPass123 as viewer')['action'] ?? null);
        $this->assertSame('update_it_asset', $router->match('set the notes on ATC-LAP-000001 to spare laptop')['action'] ?? null);
        $this->assertSame('change_ticket_status', $router->match('set ticket TKT-00004 to in progress')['action'] ?? null);
        $this->assertSame('update_controlled_document', $router->match('set the title of ATC-F-HR-003 to Leave Policy')['action'] ?? null);
        $this->assertSame('change_it_asset_status', $router->match('Can you change the status from AVAILABLE to LOST?')['action'] ?? null);
    }

    public function test_admin_ask_saves_user_asset_ticket_and_document(): void
    {
        $asset = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets', [
                'serial_number' => 'SN-NOTES-1001',
                'asset_type' => 'laptop',
                'manufacturer' => 'Dell',
                'product_name' => 'Latitude 5520',
                'status' => 'AVAILABLE',
            ]);
        $asset->assertCreated();

        tenancy()->initialize($this->testTenant);
        TicketingTicket::query()->create([
            'ticket_number' => 4,
            'title' => 'Access request',
            'description' => 'Need access',
            'status' => TicketingTicket::STATUS_OPEN,
            'priority' => TicketingTicket::PRIORITY_NORMAL,
            'requester_id' => $this->testTenantAdmin->id,
        ]);
        ControlledDocument::query()->create([
            'document_code' => 'ATC-F-HR-003',
            'title' => 'HR Policy',
            'document_type' => 'P',
            'department' => 'HR',
            'current_revision' => 1,
            'status' => 'published',
        ]);
        tenancy()->end();

        $headers = $this->tenantApiHeaders();
        $user = $this->actingAsTenantAdmin()->withHeaders($headers)->postJson('/api/v1/assistant/ask', [
            'question' => 'create user jane.viewer@test.localhost named Jane Viewer password TempPass123 as viewer',
            'module_context' => 'team_access',
            'page_path' => '/admin/users',
        ]);
        $user->assertOk()->assertJsonPath('data.answer', 'jane.viewer@test.localhost was created and is active.');

        $notes = $this->actingAsTenantAdmin()->withHeaders($headers)->postJson('/api/v1/assistant/ask', [
            'question' => 'set the notes on ATC-LAP-000001 to spare laptop',
            'module_context' => 'asset_management',
            'page_path' => '/assets',
        ]);
        $notes->assertOk()->assertJsonPath('data.answer', 'ATC-LAP-000001 was updated.');

        $ticket = $this->actingAsTenantAdmin()->withHeaders($headers)->postJson('/api/v1/assistant/ask', [
            'question' => 'set ticket TKT-00004 to in progress',
            'module_context' => 'ticketing',
            'page_path' => '/tickets',
        ]);
        $ticket->assertOk()->assertJsonPath('data.answer', 'TKT-00004 is now in progress');

        $document = $this->actingAsTenantAdmin()->withHeaders($headers)->postJson('/api/v1/assistant/ask', [
            'question' => 'set the title of ATC-F-HR-003 to Leave Policy',
            'module_context' => 'document_register',
            'page_path' => '/controlled-documents',
        ]);
        $document->assertOk()->assertJsonPath('data.answer', 'ATC-F-HR-003 title is now Leave Policy.');

        tenancy()->initialize($this->testTenant);
        $created = TenantUser::query()->where('email', 'jane.viewer@test.localhost')->first();
        $this->assertInstanceOf(TenantUser::class, $created);
        $this->assertTrue($created->isActive());
        $this->assertSame('spare laptop', Asset::query()->where('asset_tag', 'ATC-LAP-000001')->value('notes'));
        $this->assertSame(TicketingTicket::STATUS_IN_PROGRESS, TicketingTicket::query()->where('ticket_number', 4)->value('status'));
        $this->assertSame('Leave Policy', ControlledDocument::query()->where('document_code', 'ATC-F-HR-003')->value('title'));
        tenancy()->end();
    }
}
