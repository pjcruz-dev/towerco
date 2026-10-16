<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentReportDefinition;
use App\Modules\Documents\Services\ControlledDocumentSavedReportService;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\Identity\Models\TenantUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ControlledDocumentSavedReportTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'documents', 'document_register', 'e_approval',
            ],
            'toweros.tenant_files.disk' => 'tenant_files',
        ]);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        Storage::fake('tenant_files');
    }

    public function test_saved_report_runs_filtered_csv_and_stays_with_its_owner(): void
    {
        tenancy()->initialize($this->testTenant);
        $this->document('ATC-OMD-P-010', 'Recent policy', [
            'department' => 'OMD',
            'published_at' => now()->subDays(2),
        ]);
        $this->document('ATC-OMD-P-011', 'Old policy', [
            'department' => 'OMD',
            'published_at' => now()->subDays(40),
        ]);
        tenancy()->end();

        $created = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/documents/controlled/reports/saved', [
                'name' => 'OMD last 30 days',
                'filters' => [
                    'period' => 30,
                    'department' => 'OMD',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'OMD last 30 days')
            ->assertJsonPath('data.filters.period', 30)
            ->assertJsonPath('data.filters.department', 'OMD');

        $id = (string) $created->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled/reports/saved')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/documents/controlled/reports/saved', [
                'name' => 'OMD last 30 days',
                'filters' => ['period' => 90],
            ])
            ->assertStatus(422);

        $run = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/documents/controlled/reports/saved/'.$id.'/run');
        $run->assertOk();
        $csv = $run->streamedContent();
        $this->assertStringContainsString('ATC-OMD-P-010', $csv);
        $this->assertStringNotContainsString('ATC-OMD-P-011', $csv);

        tenancy()->initialize($this->testTenant);
        $other = TenantUser::query()->create([
            'name' => 'Other Reader',
            'email' => 'other.reader@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $service = app(ControlledDocumentSavedReportService::class);
        $caught = false;
        try {
            $service->findForUser($other, $id);
        } catch (ValidationException) {
            $caught = true;
        }
        tenancy()->end();
        $this->assertTrue($caught);

        $this->actingAs($other, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled/reports/saved')
            ->assertForbidden();
    }

    public function test_scheduled_run_stores_csv_and_skips_the_same_day(): void
    {
        tenancy()->initialize($this->testTenant);
        $this->document('ATC-OMD-P-020', 'Scheduled policy', [
            'department' => 'OMD',
            'published_at' => now()->subDay(),
        ]);
        tenancy()->end();

        $now = CarbonImmutable::now();
        $created = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/documents/controlled/reports/saved', [
                'name' => 'Morning register',
                'filters' => ['period' => 30, 'department' => 'OMD'],
                'schedule' => [
                    'enabled' => true,
                    'frequency' => 'daily',
                    'hour' => $now->hour,
                    'recipients' => ['ops@example.com'],
                ],
            ])
            ->assertCreated()
            ->json('data');

        tenancy()->initialize($this->testTenant);
        $service = app(ControlledDocumentSavedReportService::class);
        $report = ControlledDocumentReportDefinition::query()->findOrFail($created['id']);
        $this->assertTrue($service->isDue($report, $now));

        $result = $service->runScheduled($report);
        $this->assertSame(['ops@example.com'], $result['recipients']);
        $report->refresh();
        $this->assertFalse($service->isDue($report, $now));
        Storage::disk('tenant_files')->assertExists((string) $report->last_export_path);
        $this->assertStringContainsString(
            'ATC-OMD-P-020',
            (string) Storage::disk('tenant_files')->get((string) $report->last_export_path),
        );
        tenancy()->end();

        $file = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/documents/controlled/reports/saved/'.$created['id'].'/file');
        $file->assertOk();
        $this->assertStringContainsString('ATC-OMD-P-020', $file->streamedContent());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function document(string $code, string $title, array $attributes): void
    {
        ControlledDocument::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'document_code' => $code,
            'title' => $title,
            'current_revision' => 0,
            'status' => ControlledDocumentStatus::PUBLISHED,
        ], $attributes));
    }
}
