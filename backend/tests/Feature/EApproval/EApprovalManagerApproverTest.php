<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\EntraGraphAppService;
use Mockery;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalManagerApproverTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_submission_with_manager_step_assigns_resolved_approver(): void
    {
        tenancy()->initialize($this->testTenant);

        $requestor = TenantUser::query()->create([
            'name' => 'Requestor',
            'email' => 'requestor@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestor->assignRole('e_approval_requestor');

        $manager = TenantUser::query()->create([
            'name' => 'Manager',
            'email' => 'manager@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $manager->assignRole('e_approval_approver');

        tenancy()->end();

        $graph = Mockery::mock(EntraGraphAppService::class);
        $graph->shouldReceive('getManagerEmailForUser')
            ->with('requestor@test.localhost')
            ->andReturn('manager@test.localhost');
        $this->app->instance(EntraGraphAppService::class, $graph);

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Manager Step Form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'summary', 'label' => 'Summary'],
                ],
                'steps' => [
                    ['type' => 'manager', 'step_order' => 1],
                ],
            ]);

        $formRes->assertCreated();
        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['summary' => 'Need approval'],
            ]);

        $subRes->assertCreated();
        $submissionId = $subRes->json('data.id');

        tenancy()->initialize($this->testTenant);
        $approval = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', 'pending')
            ->first();
        $this->assertNotNull($approval);
        $this->assertSame((string) $manager->id, (string) $approval->approver_id);
        tenancy()->end();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
