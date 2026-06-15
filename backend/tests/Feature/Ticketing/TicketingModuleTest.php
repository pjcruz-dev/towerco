<?php

declare(strict_types=1);

namespace Tests\Feature\Ticketing;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use App\Models\TicketingTicket;
use App\Modules\Ticketing\Notifications\TicketingTicketMailNotification;
use App\Modules\Ticketing\Services\TicketingSettingsService;
use App\Modules\Ticketing\Services\TicketingSlaRunnerService;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TicketingModuleTest extends TestCase
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
                'project_one',
                'e_approval',
                'ticketing',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_dashboard_returns_kpis(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/ticketing/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'kpis',
                    'recent_tickets',
                    'message',
                ],
            ]);
    }

    public function test_can_create_ticket_with_comment_flow(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Rollout gate stuck',
                'description' => 'Cannot advance SAQ phase on rollout R-100.',
                'priority' => 'high',
                'category' => 'operations',
                'source_module' => 'project_one',
                'source_reference_type' => 'rollout',
                'source_reference_id' => '00000000-0000-0000-0000-000000000001',
                'source_label' => 'Rollout R-100',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Rollout gate stuck')
            ->assertJsonPath('data.status', 'open');

        $ticketId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/ticketing/tickets/{$ticketId}/comments", [
                'body' => 'Please check gate approval delegation.',
            ])
            ->assertCreated();

        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/ticketing/tickets/{$ticketId}");

        $show->assertOk()
            ->assertJsonPath('data.comments.0.body', 'Please check gate approval delegation.');
    }

    public function test_ticket_index_returns_paginated_list(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Index visibility test',
                'priority' => 'normal',
            ])
            ->assertCreated();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/ticketing/tickets?page=1&per_page=20');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'ticket_number', 'title', 'status', 'priority'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 1);
    }

    public function test_ticket_show_returns_detail(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Show detail test',
                'priority' => 'high',
            ]);

        $ticketId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/ticketing/tickets/{$ticketId}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Show detail test')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'ticket_number',
                    'comments',
                    'attachments',
                    'links',
                ],
            ]);
    }

    public function test_starter_plan_blocks_ticketing(): void
    {
        $this->testTenant->plan_tier = 'starter';
        $this->testTenant->save();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/ticketing/dashboard')
            ->assertStatus(422);
    }

    public function test_settings_can_be_read_and_updated(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/ticketing/settings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'it_support_email',
                    'notify_it_on_create',
                    'notify_it_on_reopen',
                    'notify_requestor_on_resolve',
                ],
            ]);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/ticketing/settings', [
                'it_support_email' => 'it@example.com',
                'notify_it_on_reopen' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.it_support_email', 'it@example.com')
            ->assertJsonPath('data.notify_it_on_reopen', true);
    }

    public function test_resolve_requires_resolution_comment(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Needs resolution comment',
            ])
            ->assertCreated();

        $ticketId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson("/api/v1/ticketing/tickets/{$ticketId}", [
                'status' => 'resolved',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['resolution_comment']);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson("/api/v1/ticketing/tickets/{$ticketId}", [
                'status' => 'resolved',
                'resolution_comment' => 'Restarted the rollout worker.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.comments.0.body', 'Restarted the rollout worker.');
    }

    public function test_requester_can_reopen_resolved_ticket_to_open(): void
    {
        Notification::fake();

        tenancy()->initialize($this->testTenant);
        $requester = TenantUser::query()->create([
            'name' => 'Ticket Requester',
            'email' => 'requester@test.localhost',
            'password' => 'password',
        ]);
        $requester->assignRole('viewer');
        tenancy()->end();

        tenancy()->initialize($this->testTenant);
        app(TicketingSettingsService::class)->setString(TicketingSettingsService::IT_SUPPORT_EMAIL, 'it@example.com');
        tenancy()->end();

        $create = $this->actingAs($requester, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Reopen flow test',
            ])
            ->assertCreated();

        $ticketId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson("/api/v1/ticketing/tickets/{$ticketId}", [
                'status' => 'resolved',
                'resolution_comment' => 'Fixed for now.',
            ])
            ->assertOk();

        $this->actingAs($requester, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson("/api/v1/ticketing/tickets/{$ticketId}", [
                'status' => 'open',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.resolved_at', null)
            ->assertJsonPath('data.closed_at', null);

        Notification::assertSentOnDemand(
            TicketingTicketMailNotification::class,
            function (TicketingTicketMailNotification $notification, array $channels, object $notifiable): bool {
                return in_array('it@example.com', $notifiable->routes['mail'] ?? [], true);
            },
        );
    }

    public function test_can_create_ticket_from_e_approval_source_with_links(): void
    {
        $submissionId = '00000000-0000-0000-0000-000000000099';

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Submission blocked',
                'description' => 'Workflow stuck on step 2.',
                'source_module' => 'e_approval',
                'source_reference_type' => 'submission',
                'source_reference_id' => $submissionId,
                'source_label' => 'EA-00042',
                'links' => [
                    [
                        'link_module' => 'e_approval',
                        'link_type' => 'submission',
                        'link_id' => $submissionId,
                        'link_label' => 'EA-00042',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.source_module', 'e_approval')
            ->assertJsonPath('data.source_label', 'EA-00042')
            ->assertJsonPath('data.links.0.link_type', 'submission');

        $ticketId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/ticketing/tickets?source_module=e_approval&source_reference_id='.$submissionId)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ticketId);
    }

    public function test_assignee_receives_notification_when_assigned(): void
    {
        Notification::fake();

        tenancy()->initialize($this->testTenant);
        $assignee = TenantUser::query()->create([
            'name' => 'Ticket Assignee',
            'email' => 'assignee@test.localhost',
            'password' => 'password',
        ]);
        $assignee->assignRole('manager');
        tenancy()->end();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Assign on create',
            ])
            ->assertCreated();

        $ticketId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson("/api/v1/ticketing/tickets/{$ticketId}", [
                'assignee_id' => (string) $assignee->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', (string) $assignee->id);

        Notification::assertSentTo($assignee, TicketingTicketMailNotification::class);
    }

    public function test_internal_comments_hidden_from_requestor(): void
    {
        tenancy()->initialize($this->testTenant);
        $requester = TenantUser::query()->create([
            'name' => 'Comment Requester',
            'email' => 'comment-requester@test.localhost',
            'password' => 'password',
        ]);
        $requester->assignRole('viewer');
        tenancy()->end();

        $create = $this->actingAs($requester, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Internal comment visibility',
            ])
            ->assertCreated();

        $ticketId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/ticketing/tickets/{$ticketId}/comments", [
                'body' => 'IT-only triage note.',
                'is_internal' => true,
            ])
            ->assertCreated();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/ticketing/tickets/{$ticketId}/comments", [
                'body' => 'Public update for requester.',
            ])
            ->assertCreated();

        $requesterView = $this->actingAs($requester, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/ticketing/tickets/{$ticketId}")
            ->assertOk();

        $this->assertCount(1, $requesterView->json('data.comments'));
        $this->assertSame('Public update for requester.', $requesterView->json('data.comments.0.body'));

        $this->actingAs($requester, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/ticketing/tickets/{$ticketId}/comments", [
                'body' => 'Requester tries internal.',
                'is_internal' => true,
            ])
            ->assertForbidden();
    }

    public function test_settings_supports_custom_categories_and_sla(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/ticketing/settings', [
                'categories' => ['general', 'noc', 'field_ops'],
                'sla_response_minutes' => 60,
                'sla_escalation_minutes' => 120,
                'sla_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.categories', ['general', 'noc', 'field_ops'])
            ->assertJsonPath('data.sla_response_minutes', 60);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/ticketing/metadata')
            ->assertOk()
            ->assertJsonFragment(['noc']);
    }

    public function test_sla_runner_sends_reminder_for_overdue_ticket(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TicketingSettingsService::class)->setString(TicketingSettingsService::SLA_RESPONSE_MINUTES, '1');
        app(TicketingSettingsService::class)->setString(TicketingSettingsService::SLA_ESCALATION_MINUTES, '120');
        tenancy()->end();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'SLA overdue test',
            ])
            ->assertCreated();

        $ticketId = (string) $create->json('data.id');

        tenancy()->initialize($this->testTenant);
        TicketingTicket::query()->whereKey($ticketId)->update([
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
        $result = app(TicketingSlaRunnerService::class)->run();
        tenancy()->end();

        $this->assertSame(1, $result['reminders']);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/ticketing/tickets/{$ticketId}")
            ->assertOk()
            ->assertJsonPath('data.sla_status', 'at_risk');
    }

    public function test_can_create_ticket_from_sites_source(): void
    {
        tenancy()->initialize($this->testTenant);
        $site = Site::query()->create([
            'site_code' => 'SITE-P3',
            'name' => 'Phase 3 Site',
            'status' => 'active',
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Site access issue',
                'source_module' => 'sites',
                'source_reference_type' => 'site',
                'source_reference_id' => (string) $site->id,
                'source_label' => 'SITE-P3',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source_module', 'sites');
    }

    public function test_non_manage_user_only_sees_own_tickets(): void
    {
        tenancy()->initialize($this->testTenant);
        $requester = TenantUser::query()->create([
            'name' => 'Other Requester',
            'email' => 'other@test.localhost',
            'password' => 'password',
        ]);
        $requester->assignRole('viewer');
        tenancy()->end();

        $adminTicket = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Admin ticket hidden from requester',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($requester, 'sanctum')t
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/ticketing/tickets')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($requester, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/ticketing/tickets/{$adminTicket}")
            ->assertForbidden();
    }
}
