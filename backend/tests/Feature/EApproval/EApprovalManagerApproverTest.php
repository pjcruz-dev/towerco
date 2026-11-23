<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\EntraGraphAppService;
use App\Modules\Identity\Support\EntraDirectoryPerson;
use App\Modules\Identity\Support\EntraManagerLookupResult;
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
        $graph->shouldReceive('lookupManagerForEmail')
            ->with('requestor@test.localhost')
            ->andReturn(new EntraManagerLookupResult(
                ok: true,
                code: EntraManagerLookupResult::CODE_OK,
                message: 'Manager found in Microsoft Entra.',
                manager: new EntraDirectoryPerson('mgr-1', 'manager@test.localhost', 'Manager'),
            ));
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

    public function test_reporting_line_walks_manager_then_stops_at_director(): void
    {
        tenancy()->initialize($this->testTenant);

        $requestor = $this->makeUser('Aiman Pangandaman', 'aiman@test.localhost', 'e_approval_requestor');
        $ramon = $this->makeUser('Ramon Hernandez', 'ramon@test.localhost', 'e_approval_approver');
        $pilar = $this->makeUser('Demetrio Pilar', 'pilar@test.localhost', 'e_approval_approver');
        $finance = $this->makeUser('Finance', 'finance@test.localhost', 'e_approval_approver');

        tenancy()->end();

        $lookups = 0;
        $graph = Mockery::mock(EntraGraphAppService::class);
        $graph->shouldReceive('lookupManagerForEmail')->andReturnUsing(function (string $email) use (&$lookups): EntraManagerLookupResult {
            $lookups++;

            return match (strtolower($email)) {
                'aiman@test.localhost' => $this->managerLookup('ramon-entra', 'ramon@test.localhost', 'Ramon Hernandez', 'Project Manager'),
                'ramon@test.localhost' => $this->managerLookup('pilar-entra', 'pilar@test.localhost', 'Demetrio Pilar', 'Director'),
                default => $this->managerLookup('vp-entra', 'vp@test.localhost', 'Vice President', 'Vice President'),
            };
        });
        $this->app->instance(EntraGraphAppService::class, $graph);

        $formId = $this->publishManagerForm($finance, chain: true);

        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/forms/{$formId}");
        $show->assertOk()
            ->assertJsonPath('data.steps.0.manager_chain', true)
            ->assertJsonPath('data.steps.0.manager_chain_stop_titles', 'Director');

        $subRes = $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['summary' => 'Cash advance'],
            ]);
        $subRes->assertCreated();
        $submissionId = (string) $subRes->json('data.id');

        tenancy()->initialize($this->testTenant);
        $compiled = EApprovalWorkflowStep::query()
            ->where('compiled_for_submission_id', $submissionId)
            ->orderBy('step_order')
            ->get();
        $this->assertSame(
            [(string) $ramon->id, (string) $pilar->id, (string) $finance->id],
            $compiled->pluck('approver_id')->map(static fn ($id): string => (string) $id)->all(),
        );
        $this->assertSame([1, 2, 3], $compiled->pluck('step_order')->map(static fn ($order): int => (int) $order)->all());
        $this->assertSame('Project Manager', $compiled[0]->label);
        $this->assertSame('Director', $compiled[1]->label);
        $pending = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', 'pending')
            ->first();
        $this->assertSame((string) $ramon->id, (string) $pending?->approver_id);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}/workflow-preview")
            ->assertOk()
            ->assertJsonPath('data.resolved_steps.0.resolved_user_name', 'Ramon Hernandez')
            ->assertJsonPath('data.resolved_steps.0.role_label', 'Project Manager')
            ->assertJsonPath('data.resolved_steps.1.resolved_user_name', 'Demetrio Pilar')
            ->assertJsonPath('data.resolved_steps.1.role_label', 'Director')
            ->assertJsonPath('data.resolved_steps.2.resolved_user_name', 'Finance');

        $this->assertSame(2, $lookups);
    }

    public function test_reporting_line_is_only_the_director_when_the_requestor_reports_directly(): void
    {
        tenancy()->initialize($this->testTenant);

        $requestor = $this->makeUser('Elmer Manuel', 'elmer@test.localhost', 'e_approval_requestor');
        $pilar = $this->makeUser('Demetrio Pilar', 'pilar@test.localhost', 'e_approval_approver');
        $finance = $this->makeUser('Finance', 'finance@test.localhost', 'e_approval_approver');

        tenancy()->end();

        $graph = Mockery::mock(EntraGraphAppService::class);
        $graph->shouldReceive('lookupManagerForEmail')->andReturnUsing(function (string $email): EntraManagerLookupResult {
            return match (strtolower($email)) {
                'elmer@test.localhost' => $this->managerLookup('pilar-entra', 'pilar@test.localhost', 'Demetrio Pilar', 'Director'),
                default => $this->managerLookup('vp-entra', 'vp@test.localhost', 'Vice President', 'Vice President'),
            };
        });
        $this->app->instance(EntraGraphAppService::class, $graph);

        $formId = $this->publishManagerForm($finance, chain: true);

        $subRes = $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['summary' => 'Direct to director'],
            ]);
        $subRes->assertCreated();
        $submissionId = (string) $subRes->json('data.id');

        tenancy()->initialize($this->testTenant);
        $compiled = EApprovalWorkflowStep::query()
            ->where('compiled_for_submission_id', $submissionId)
            ->orderBy('step_order')
            ->get();
        $this->assertSame(
            [(string) $pilar->id, (string) $finance->id],
            $compiled->pluck('approver_id')->map(static fn ($id): string => (string) $id)->all(),
        );
        $this->assertSame([1, 2], $compiled->pluck('step_order')->map(static fn ($order): int => (int) $order)->all());
        tenancy()->end();
    }

    private function makeUser(string $name, string $email, string $role): TenantUser
    {
        $user = TenantUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function managerLookup(string $entraId, string $email, string $name, string $jobTitle): EntraManagerLookupResult
    {
        return new EntraManagerLookupResult(
            ok: true,
            code: EntraManagerLookupResult::CODE_OK,
            message: 'Manager found in Microsoft Entra.',
            manager: new EntraDirectoryPerson($entraId, $email, $name, $jobTitle),
        );
    }

    private function publishManagerForm(TenantUser $laterApprover, bool $chain): string
    {
        $step = ['type' => 'manager', 'step_order' => 1];
        if ($chain) {
            $step['manager_chain'] = true;
            $step['manager_chain_stop_titles'] = 'Director';
            $step['manager_chain_max_levels'] = 6;
        }

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Reporting line form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'summary', 'label' => 'Summary'],
                ],
                'steps' => [
                    $step,
                    ['type' => 'user', 'approverId' => (string) $laterApprover->id, 'step_order' => 2],
                ],
            ]);
        $formRes->assertCreated();

        return (string) $formRes->json('data.form.id');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
