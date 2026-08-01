<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Jobs\GenerateEApprovalExportJob;
use App\Modules\EApproval\Models\EApprovalExportHistory;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalReportService;
use App\Modules\EApproval\Support\EApprovalExportHistoryStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalAsyncExportTest extends TestCase
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
        Storage::fake((string) config('toweros.tenant_files.disk', 'tenant_files'));
    }

    public function test_force_async_export_queues_job_and_completes_download(): void
    {
        Queue::fake();

        tenancy()->initialize($this->testTenant);
        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Async Form',
            'category' => 'ops',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'OPS',
            'doc_type_code' => 'AS',
        ]);
        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'AS-001',
            'status' => EApprovalSubmissionStatus::APPROVED,
            'current_step' => 1,
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/submissions/export?async=1&format=csv');

        $response->assertStatus(202)
            ->assertJsonPath('data.async', true)
            ->assertJsonPath('data.history.status', EApprovalExportHistoryStatus::QUEUED);

        $historyId = (string) $response->json('data.history.id');
        $this->assertNotEmpty($historyId);

        Queue::assertPushed(GenerateEApprovalExportJob::class, function (GenerateEApprovalExportJob $job) use ($historyId): bool {
            return $job->historyId === $historyId
                && $job->tenantId === (string) $this->testTenant->id;
        });

        tenancy()->initialize($this->testTenant);
        app(EApprovalReportService::class)->processQueuedHistory($historyId);
        $history = EApprovalExportHistory::query()->findOrFail($historyId);
        $this->assertSame(EApprovalExportHistoryStatus::COMPLETED, $history->status);
        $this->assertNotEmpty($history->file_path);
        $this->assertTrue(Storage::disk((string) $history->disk)->exists((string) $history->file_path));
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/export-history/'.$historyId)
            ->assertOk()
            ->assertJsonPath('data.status', EApprovalExportHistoryStatus::COMPLETED)
            ->assertJsonPath('data.download.stream', true);

        $download = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/export-history/'.$historyId.'/download');

        $download->assertOk();
        $this->assertStringContainsString('text/csv', (string) $download->headers->get('Content-Type'));
    }

    public function test_sync_export_still_streams_under_threshold(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export?format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertNotNull($response->headers->get('X-Export-History-Id'));
    }
}
