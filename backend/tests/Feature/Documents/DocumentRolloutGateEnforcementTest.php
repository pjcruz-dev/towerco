<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\DocumentSiteNode;
use App\Modules\Documents\Models\DocumentSiteWorkspace;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DocumentRolloutGateEnforcementTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private Site $site;

    private RolloutProgram $rollout;

    private RolloutTimelinePhase $phase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'project_one', 'sites', 'documents',
            ],
            'toweros.documents.gate_required_node_keys' => ['saq_phase_1', 'col'],
            'toweros.documents.gate_enforcement' => [
                'enabled' => true,
                'phase_keys' => ['moc_col'],
            ],
        ]);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->testTenant->update(['plan_tier' => 'professional']);

        Storage::fake('tenant_files');
        config(['toweros.tenant_files.disk' => 'tenant_files']);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $this->site = Site::query()->create([
            'site_code' => 'GATE-001',
            'name' => 'Gate Enforcement Site',
            'status' => 'active',
        ]);

        $this->rollout = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-GATE-001',
            'site_id' => $this->site->id,
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'permitting',
            'endorsement_date' => '2026-04-01',
            'sla_working_days' => 120,
        ]);

        $this->phase = RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $this->rollout->id,
            'phase_key' => 'moc_col',
            'label' => 'MOC + COL',
            'owner_role' => 'saq',
            'anchor' => 'tssr_approved',
            'working_day_start' => 1,
            'working_day_end' => 8,
            'gate_status' => 'pending',
            'gate_label' => 'eLAS IRR Pass',
            'sort_order' => 1,
        ]);

        $workspace = app(DocumentWorkspaceService::class)->ensureForSite($this->site);
        $workspace->rollout_program_id = $this->rollout->id;
        $workspace->save();

        tenancy()->end();
    }

    public function test_gate_pass_blocked_when_binder_checklist_incomplete(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/rollout-phases/'.$this->phase->id.'/gate', [
                'gate_status' => 'passed',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['gate_status']);
    }

    public function test_gate_pass_allowed_when_required_final_documents_present(): void
    {
        tenancy()->initialize($this->testTenant);
        $workspace = DocumentSiteWorkspace::query()->where('site_id', $this->site->id)->firstOrFail();
        $nodes = DocumentSiteNode::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('node_key', ['saq_phase_1', 'col'])
            ->get();
        tenancy()->end();

        foreach ($nodes as $node) {
            $docId = $this->actingAsTenantAdmin()
                ->withHeaders($this->tenantApiHeaders())
                ->post("/api/v1/sites/{$this->site->id}/documents/files", [
                    'site_node_id' => $node->id,
                    'file' => UploadedFile::fake()->create($node->node_key.'.pdf', 50, 'application/pdf'),
                ])
                ->assertCreated()
                ->json('data.id');
            $this->actingAsTenantAdmin()
                ->withHeaders($this->tenantApiHeaders())
                ->patchJson("/api/v1/documents/files/{$docId}/metadata", [
                    'status' => DocumentStatus::FINAL,
                ])
                ->assertOk();
        }

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/rollout-phases/'.$this->phase->id.'/gate', [
                'gate_status' => 'passed',
            ])
            ->assertOk()
            ->assertJsonPath('data.timeline_phases.0.gate_status', 'passed')
            ->assertJsonPath('data.timeline_phases.0.document_binder_gate.complete', true);
    }

    public function test_gate_waived_bypasses_binder_checklist(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/rollout-phases/'.$this->phase->id.'/gate', [
                'gate_status' => 'waived',
            ])
            ->assertOk()
            ->assertJsonPath('data.timeline_phases.0.gate_status', 'waived');
    }

    public function test_non_enforced_phase_passes_without_documents(): void
    {
        tenancy()->initialize($this->testTenant);
        $otherPhase = RolloutTimelinePhase::query()->create([
            'id' => (string) Str::uuid(),
            'rollout_program_id' => $this->rollout->id,
            'phase_key' => 'construction',
            'label' => 'Construction',
            'owner_role' => 'cme',
            'anchor' => 'tssr_approved',
            'working_day_start' => 20,
            'working_day_end' => 60,
            'gate_status' => 'pending',
            'sort_order' => 2,
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/rollout-phases/'.$otherPhase->id.'/gate', [
                'gate_status' => 'passed',
            ])
            ->assertOk();
    }
}
