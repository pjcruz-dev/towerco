<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\DocumentSiteNode;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DocumentSiteBinderTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'project_one', 'e_approval', 'ticketing', 'sites', 'documents',
            ],
        ]);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->testTenant->update(['plan_tier' => 'professional']);

        Storage::fake('tenant_files');
        config([
            'toweros.tenant_files.disk' => 'tenant_files',
            'toweros.documents.max_size_kb' => 51200,
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $this->site = Site::query()->create([
            'site_code' => 'AT-900',
            'name' => 'Binder Test Site',
            'status' => 'active',
        ]);
        tenancy()->end();
    }

    public function test_workspace_seeds_default_binder_and_uploads_file(): void
    {
        $workspace = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/workspace");

        $workspace->assertOk();
        $nodes = $workspace->json('data.nodes');
        $this->assertNotEmpty($nodes);

        $uploadNode = collect($nodes)->firstWhere('node_key', 'saq_phase_1');
        $this->assertNotNull($uploadNode);

        $permitsNode = collect($nodes)->firstWhere('node_key', 'permits_clearances');
        $this->assertNotNull($permitsNode);
        $this->assertSame('Permits & clearances', $permitsNode['label']);

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/sites/{$this->site->id}/documents/files", [
                'site_node_id' => $uploadNode['id'],
                'file' => UploadedFile::fake()->create('lease.pdf', 100, 'application/pdf'),
            ]);

        $upload->assertCreated();
        $this->assertSame('lease.pdf', $upload->json('data.title'));
        $this->assertNotEmpty($upload->json('data.last_touched_by.name'));
    }

    public function test_add_lessor_creates_repeatable_folder(): void
    {
        $lessor = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/sites/{$this->site->id}/documents/lessors", [
                'lessor_name' => 'Maria Santos',
                'lessor_contact' => '09171234567',
            ]);

        $lessor->assertCreated();
        $this->assertStringContainsString('Maria Santos', (string) $lessor->json('data.instance.label'));
    }

    public function test_existing_workspace_backfills_missing_permits_clearances_folder(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/workspace")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        DocumentSiteNode::query()
            ->where('node_key', 'permits_clearances')
            ->delete();
        $this->assertNull(
            DocumentSiteNode::query()->where('node_key', 'permits_clearances')->first(),
        );
        tenancy()->end();

        $workspace = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/workspace");

        $workspace->assertOk();
        $permitsNode = collect($workspace->json('data.nodes'))->firstWhere('node_key', 'permits_clearances');
        $this->assertNotNull($permitsNode);
        $this->assertSame('Permits & clearances', $permitsNode['label']);
    }

    public function test_rollout_options_lists_site_linked_program_even_when_not_in_global_index_page(): void
    {
        tenancy()->initialize($this->testTenant);

        $matched = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-SITE-LINK-'.uniqid('', true),
            'site_id' => $this->site->id,
            'mno' => 'smart',
            'project_type' => 'bts',
            'status' => 'permitting',
            'endorsement_date' => '2026-04-01',
            'sla_working_days' => 120,
        ]);

        $other = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-OTHER-'.uniqid('', true),
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'permitting',
            'endorsement_date' => '2026-04-01',
            'sla_working_days' => 120,
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/rollout-options");

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('rollout_ref')->all();
        $this->assertContains($matched->rollout_ref, $refs);
        $this->assertNotContains($other->rollout_ref, $refs);

        $matchedRow = collect($response->json('data'))->firstWhere('rollout_ref', $matched->rollout_ref);
        $this->assertTrue($matchedRow['site_match']);
    }

    public function test_rollout_options_includes_manually_linked_rollout(): void
    {
        tenancy()->initialize($this->testTenant);

        $linked = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-MANUAL-LINK',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'permitting',
            'endorsement_date' => '2026-04-01',
            'sla_working_days' => 120,
        ]);

        $workspace = app(DocumentWorkspaceService::class)->ensureForSite($this->site);
        $workspace->rollout_program_id = $linked->id;
        $workspace->save();

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/rollout-options");

        $response->assertOk();
        $this->assertContains('RP-MANUAL-LINK', collect($response->json('data'))->pluck('rollout_ref')->all());
    }
}
