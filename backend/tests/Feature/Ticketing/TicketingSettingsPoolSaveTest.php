<?php

declare(strict_types=1);

namespace Tests\Feature\Ticketing;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TicketingSettingsPoolSaveTest extends TestCase
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

    public function test_can_save_it_assignee_pool_with_full_payload(): void
    {
        tenancy()->initialize($this->testTenant);
        $admin = TenantUser::query()->orderBy('created_at')->first();
        $other = TenantUser::factory()->create(['is_active' => true]);
        $adminId = (string) $admin->id;
        $otherId = (string) $other->id;
        tenancy()->end();

        $payload = [
            'it_support_email' => 'it@example.com',
            'notify_it_on_create' => true,
            'notify_it_on_reopen' => true,
            'notify_requestor_on_resolve' => true,
            'notify_assignee_on_assign' => true,
            'it_assignee_user_ids' => [$adminId, $otherId],
            'sla_enabled' => true,
            'sla_response_minutes' => 480,
            'sla_escalation_minutes' => 1440,
            'auto_close_resolved_after_days' => 3,
            'categories' => [[
                'id' => 'operations',
                'label' => 'Operations',
                'sla_response_minutes' => null,
                'sla_escalation_minutes' => null,
            ]],
            'assignment_rules' => [],
            'teams_webhook_url' => '',
            'notify_teams_on_create' => false,
            'notify_teams_on_sla_reminder' => true,
            'notify_teams_on_sla_escalation' => true,
        ];

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/ticketing/settings', $payload);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertOk()
            ->assertJsonPath('data.it_assignee_pool_configured', true);
    }

    public function test_pool_save_with_out_of_pool_assignment_rule(): void
    {
        tenancy()->initialize($this->testTenant);
        $admin = TenantUser::query()->orderBy('created_at')->first();
        $other = TenantUser::factory()->create(['is_active' => true]);
        $outsider = TenantUser::factory()->create(['is_active' => true]);
        $adminId = (string) $admin->id;
        $otherId = (string) $other->id;
        $outsiderId = (string) $outsider->id;
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/ticketing/settings', [
                'it_assignee_user_ids' => [$adminId, $otherId],
                'categories' => [['id' => 'operations', 'label' => 'Operations']],
                'assignment_rules' => [[
                    'category' => 'operations',
                    'assignee_id' => $outsiderId,
                    'enabled' => true,
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.it_assignee_pool_configured', true)
            ->assertJsonPath('data.assignment_rules', []);
    }
}
