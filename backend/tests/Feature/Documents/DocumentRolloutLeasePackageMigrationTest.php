<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentSiteNode;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Rollout\Models\TenantRolloutFile;
use App\Modules\Rollout\Support\RolloutFileContext;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DocumentRolloutLeasePackageMigrationTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'project_one', 'sites', 'documents',
            ],
        ]);

        Storage::fake('tenant_files');
        config([
            'toweros.tenant_files.disk' => 'tenant_files',
            'toweros.documents.max_size_kb' => 51200,
        ]);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->testTenant->update(['plan_tier' => 'professional']);
    }

    public function test_migrate_lease_package_copies_files_into_site_binder(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $rollout = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-LEASE-MIG',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 120,
        ]);

        $site = Site::query()->create([
            'site_code' => 'LEASE-001',
            'name' => 'Lease Migration Site',
            'status' => 'active',
        ]);
        $rollout->site_id = $site->id;
        $rollout->save();

        $storedPath = $this->testTenant->id.'/rollout/'.$rollout->id.'/'.RolloutFileContext::LEASE_DOCUMENT.'/lease.pdf';
        Storage::disk('tenant_files')->put($storedPath, 'lease-content');

        $rolloutFile = TenantRolloutFile::query()->create([
            'id' => (string) Str::uuid(),
            'rollout_program_id' => $rollout->id,
            'context' => RolloutFileContext::LEASE_DOCUMENT,
            'original_filename' => 'lease.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => 13,
            'uploaded_by_id' => $this->testTenantAdmin->id,
        ]);

        $candidate = SiteCandidate::query()->create([
            'rollout_program_id' => $rollout->id,
            'candidate_number' => 1,
            'status' => 'selected',
            'label' => 'Candidate 1',
            'lessor_name' => 'Maria Santos',
            'lease_package' => [
                'lessor_id_type' => 'gov_id',
                'lease_term_months' => 120,
                'documents' => [
                    ['file_id' => $rolloutFile->id, 'label' => 'Signed lease'],
                ],
            ],
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/documents/migrate-lease-package');

        $response->assertOk()
            ->assertJsonPath('data.migrated', 1)
            ->assertJsonPath('data.skipped', 0);

        tenancy()->initialize($this->testTenant);
        $document = Document::query()->where('source_rollout_file_id', $rolloutFile->id)->first();
        $this->assertNotNull($document);
        $this->assertSame('Signed lease', $document->title);
        $this->assertSame('final', $document->status);
        $this->assertTrue(Storage::disk('tenant_files')->exists($document->stored_path));

        $workspace = app(DocumentWorkspaceService::class)->ensureForSite($site);
        $lessorNode = DocumentSiteNode::query()
            ->where('workspace_id', $workspace->id)
            ->where('lessor_name', 'Maria Santos')
            ->first();
        $this->assertNotNull($lessorNode);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/documents/migrate-lease-package')
            ->assertOk()
            ->assertJsonPath('data.migrated', 0)
            ->assertJsonPath('data.skipped', 1);
    }

    public function test_candidate_select_auto_migrates_lease_package_when_documents_enabled(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $rollout = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-LEASE-AUTO',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 120,
        ]);

        $storedPath = $this->testTenant->id.'/rollout/'.$rollout->id.'/'.RolloutFileContext::LEASE_DOCUMENT.'/auto-lease.pdf';
        Storage::disk('tenant_files')->put($storedPath, 'auto-lease');

        $rolloutFile = TenantRolloutFile::query()->create([
            'id' => (string) Str::uuid(),
            'rollout_program_id' => $rollout->id,
            'context' => RolloutFileContext::LEASE_DOCUMENT,
            'original_filename' => 'auto-lease.pdf',
            'stored_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'uploaded_by_id' => $this->testTenantAdmin->id,
        ]);

        $candidate = SiteCandidate::query()->create([
            'rollout_program_id' => $rollout->id,
            'candidate_number' => 1,
            'status' => 'scouted',
            'label' => 'Auto Candidate',
            'lease_package' => [
                'documents' => [
                    ['file_id' => $rolloutFile->id, 'label' => 'Lease copy'],
                ],
            ],
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/candidates/'.$candidate->id.'/select')
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $this->assertTrue(
            Document::query()->where('source_rollout_file_id', $rolloutFile->id)->exists(),
        );
        tenancy()->end();
    }
}
