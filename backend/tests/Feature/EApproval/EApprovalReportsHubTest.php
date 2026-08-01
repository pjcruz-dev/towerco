<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalExportHistory;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalReportDefinition;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalReportsHubTest extends TestCase
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

    public function test_can_create_list_update_and_delete_saved_report(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/reports', [
                'name' => 'Weekly approved',
                'format' => 'xlsx',
                'layout' => 'submissions',
                'filters' => ['statuses' => ['approved']],
                'schedule' => [
                    'enabled' => true,
                    'frequency' => 'weekly',
                    'hour' => 9,
                    'day_of_week' => 1,
                    'recipients' => ['ops@example.com'],
                ],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Weekly approved')
            ->assertJsonPath('data.schedule.enabled', true)
            ->assertJsonPath('data.schedule.recipients.0', 'ops@example.com');

        $id = $create->json('data.id');
        $this->assertNotEmpty($id);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/reports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/e-approval/reports/'.$id, [
                'name' => 'Weekly approved (v2)',
                'schedule' => ['enabled' => false],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Weekly approved (v2)')
            ->assertJsonPath('data.schedule.enabled', false);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson('/api/v1/e-approval/reports/'.$id)
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $this->assertNull(EApprovalReportDefinition::query()->find($id));
        tenancy()->end();
    }

    public function test_running_saved_report_downloads_file_and_records_history(): void
    {
        tenancy()->initialize($this->testTenant);
        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Hub Form',
            'category' => 'ops',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'OPS',
            'doc_type_code' => 'HUB',
        ]);
        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'HUB-001',
            'status' => EApprovalSubmissionStatus::APPROVED,
            'current_step' => 1,
        ]);
        $report = EApprovalReportDefinition::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->testTenantAdmin->id,
            'name' => 'Hub export',
            'filters_json' => ['form_id' => $form->id, 'statuses' => ['approved']],
            'columns_json' => ['document_no', 'status'],
            'layout' => 'submissions',
            'format' => 'csv',
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/e-approval/reports/'.$report->id.'/run');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertNotNull($response->headers->get('X-Export-History-Id'));

        tenancy()->initialize($this->testTenant);
        $this->assertSame(1, EApprovalExportHistory::query()->where('report_definition_id', $report->id)->count());
        $this->assertNotNull(EApprovalReportDefinition::query()->find($report->id)?->last_run_at);
        tenancy()->end();
    }

    public function test_export_history_lists_recent_runs(): void
    {
        tenancy()->initialize($this->testTenant);
        EApprovalExportHistory::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->testTenantAdmin->id,
            'name' => 'Ad-hoc export',
            'filters_json' => [],
            'layout' => 'submissions',
            'format' => 'csv',
            'matched_rows' => 3,
            'exported_rows' => 3,
            'truncated' => false,
            'status' => 'completed',
            'triggered_by' => 'manual',
            'filename' => 'demo.csv',
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/export-history')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ad-hoc export')
            ->assertJsonPath('data.0.exported_rows', 3);
    }

    public function test_adhoc_export_records_history(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export');

        $response->assertOk();
        $this->assertNotNull($response->headers->get('X-Export-History-Id'));

        tenancy()->initialize($this->testTenant);
        $this->assertGreaterThanOrEqual(1, EApprovalExportHistory::query()->count());
        tenancy()->end();
    }
}
