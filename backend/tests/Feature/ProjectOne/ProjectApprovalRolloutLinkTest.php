<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProjectOne\Models\ProjectApproval;
use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProjectApprovalRolloutLinkTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tenant_files');
        config(['toweros.tenant_files.disk' => 'tenant_files']);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_create_approval_linked_to_rollout_with_attachment(): void
    {
        tenancy()->initialize($this->testTenant);
        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-APPROVAL-LINK',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
        ]);
        tenancy()->end();

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/project-one/files', [
                'file' => UploadedFile::fake()->create('approval.pdf', 100, 'application/pdf'),
                'context' => 'approval_attachment',
                'rollout_id' => $rollout->id,
            ]);

        $upload->assertCreated();
        $fileId = $upload->json('data.id');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/approvals', [
                'approval_type' => 'lease_review',
                'title' => 'Lease package review',
                'requester' => 'SAQ Lead',
                'sla_risk' => 'high',
                'rollout_program_id' => $rollout->id,
                'attachment_file_ids' => [$fileId],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.rollout.rollout_ref', 'RP-APPROVAL-LINK')
            ->assertJsonPath('data.attachments.0.file_id', $fileId);

        tenancy()->initialize($this->testTenant);
        $approval = ProjectApproval::query()->findOrFail($response->json('data.id'));
        $this->assertSame($rollout->id, $approval->rollout_program_id);
        $this->assertSame([$fileId], $approval->attachment_file_ids);
        tenancy()->end();
    }

    public function test_approval_index_includes_rollout_ref(): void
    {
        tenancy()->initialize($this->testTenant);
        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-APPROVAL-INDEX',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
        ]);

        ProjectApproval::query()->create([
            'rollout_program_id' => $rollout->id,
            'approval_type' => 'purchase_order',
            'title' => 'PO for materials',
            'requester' => 'PMO',
            'sla_risk' => 'medium',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/approvals?status=pending');

        $response->assertOk()
            ->assertJsonPath('data.0.rollout.rollout_ref', 'RP-APPROVAL-INDEX');
    }
}
