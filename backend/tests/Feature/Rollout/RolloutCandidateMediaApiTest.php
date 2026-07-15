<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RolloutCandidateMediaApiTest extends TestCase
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

    public function test_upload_and_attach_photos_to_candidate(): void
    {
        tenancy()->initialize($this->testTenant);
        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-MEDIA-001',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 120,
        ]);
        tenancy()->end();

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/project-one/files', [
                'file' => UploadedFile::fake()->create('site.jpg', 100, 'image/jpeg'),
                'context' => 'candidate_photo',
                'rollout_id' => $rollout->id,
            ]);

        $upload->assertCreated();
        $fileId = $upload->json('data.id');
        $this->assertNotEmpty($fileId);

        $candidate = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/candidates', [
                'label' => 'Candidate with media',
                'photo_links' => [
                    ['file_id' => $fileId, 'label' => 'Frontage'],
                ],
                'lease_package' => [
                    'lessor_id_type' => 'gov_id',
                    'lease_term_months' => 120,
                    'documents' => [],
                ],
            ]);

        $candidate->assertCreated();

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts/'.$rollout->id);

        $detail->assertOk()
            ->assertJsonPath('data.candidates.0.photo_links.0.file_id', $fileId)
            ->assertJsonPath('data.candidates.0.lease_package.lessor_id_type', 'gov_id');
    }

    public function test_file_download_requires_rollout_view_permission(): void
    {
        tenancy()->initialize($this->testTenant);
        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-MEDIA-002',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 120,
        ]);
        tenancy()->end();

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/project-one/files', [
                'file' => UploadedFile::fake()->create('lease.pdf', 100, 'application/pdf'),
                'context' => 'lease_document',
                'rollout_id' => $rollout->id,
            ]);

        $fileId = $upload->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/project-one/files/'.$fileId)
            ->assertOk();
    }
}
