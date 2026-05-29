<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutGateApprovalDelegation;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutGateApprovalDelegationService;
use App\Modules\Rollout\Services\RolloutGateApproverResolver;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutGateApprovalDelegationServiceTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;
    use SeedsTenantRolloutPlaybook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();
        $this->seedTenantRolloutPlaybook();
    }

    public function test_delegate_can_act_for_delegator_role(): void
    {
        tenancy()->initialize($this->testTenant);

        /** @var TenantUser $delegator */
        $delegator = TenantUser::query()->firstOrFail();
        /** @var TenantUser $delegate */
        $delegate = TenantUser::factory()->create();

        RolloutGateApprovalDelegation::query()->create([
            'delegator_id' => $delegator->id,
            'delegate_id' => $delegate->id,
            'role_key' => 'saq',
            'valid_from' => now()->toDateString(),
            'valid_until' => null,
            'is_active' => true,
        ]);

        /** @var RolloutProgram $program */
        $program = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-DEL-'.uniqid('', true),
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
            'saq_owner_id' => $delegator->id,
        ]);

        $resolver = app(RolloutGateApproverResolver::class);

        $this->assertTrue($resolver->canActOnStep($delegate, $program, 'saq'));

        $actingFor = $resolver->actingForDelegator($delegate, $program, 'saq');
        $this->assertNotNull($actingFor);
        $this->assertSame($delegator->id, $actingFor['id']);
        $this->assertSame($delegator->name, $actingFor['name']);

        $this->assertNull($resolver->actingForDelegator($delegator, $program, 'saq'));

        tenancy()->end();
    }

    public function test_create_delegation_via_service(): void
    {
        tenancy()->initialize($this->testTenant);

        /** @var TenantUser $delegator */
        $delegator = TenantUser::query()->firstOrFail();
        /** @var TenantUser $delegate */
        $delegate = TenantUser::factory()->create();

        $service = new RolloutGateApprovalDelegationService();
        $row = $service->create($delegator, [
            'delegate_id' => $delegate->id,
            'role_key' => 'pmo',
            'notes' => 'Acting while on leave',
        ]);

        $this->assertSame($delegate->id, $row->delegate_id);
        $this->assertSame('pmo', $row->role_key);

        tenancy()->end();
    }
}
