<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ControlledDocumentReportTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'documents', 'document_register', 'e_approval',
            ],
        ]);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_overview_counts_reviews_and_recent_publications(): void
    {
        tenancy()->initialize($this->testTenant);
        $this->document('ATC-OMD-P-001', 'Leasing', [
            'department' => 'OMD',
            'document_type' => 'Policy',
            'published_at' => now(),
            'next_review_date' => now()->subDay()->toDateString(),
        ]);
        $this->document('ATC-QMS-P-002', 'Quality', [
            'department' => 'QMS',
            'document_type' => 'Procedure',
            'published_at' => now()->subDays(40),
            'next_review_date' => now()->addDays(10)->toDateString(),
        ]);
        $this->document('ATC-OLD-P-003', 'Retired', [
            'status' => ControlledDocumentStatus::OBSOLETE,
            'published_at' => now()->subDays(10),
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled/overview')
            ->assertOk()
            ->assertJsonPath('data.counts.total', 3)
            ->assertJsonPath('data.counts.published', 2)
            ->assertJsonPath('data.counts.obsolete', 1)
            ->assertJsonPath('data.counts.published_last_30_days', 1)
            ->assertJsonPath('data.counts.overdue', 1)
            ->assertJsonPath('data.counts.due_30', 1)
            ->assertJsonPath('data.recent.0.document_code', 'ATC-OMD-P-001')
            ->assertJsonFragment(['key' => 'OMD', 'label' => 'OMD', 'value' => 1]);

        $series = $response->json('data.published_over_time');
        $this->assertCount(14, $series);
        $this->assertSame(1, (int) collect($series)->sum('value'));
        $this->assertSame(1, (int) $series[13]['value']);
    }

    public function test_report_period_excludes_older_publications_and_export_lists_the_row(): void
    {
        tenancy()->initialize($this->testTenant);
        $this->document('ATC-OMD-P-010', 'Recent policy', [
            'department' => 'OMD',
            'document_type' => 'Policy',
            'published_at' => now()->subDays(2),
            'next_review_date' => now()->subDay()->toDateString(),
        ]);
        $this->document('ATC-OMD-P-011', 'Old policy', [
            'department' => 'OMD',
            'document_type' => 'Policy',
            'published_at' => now()->subDays(40),
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled/reports?period=30&department=OMD')
            ->assertOk()
            ->assertJsonPath('data.counts.total', 1)
            ->assertJsonPath('data.review.0.key', 'overdue')
            ->assertJsonPath('data.review.0.value', 1);

        $export = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/documents/controlled/reports/export?period=30&department=OMD');

        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('ATC-OMD-P-010', $csv);
        $this->assertStringNotContainsString('ATC-OMD-P-011', $csv);
    }

    public function test_index_filters_by_published_from(): void
    {
        tenancy()->initialize($this->testTenant);
        $this->document('ATC-NEW-P-001', 'New', ['published_at' => now()]);
        $this->document('ATC-OLD-P-001', 'Old', ['published_at' => now()->subDays(40)]);
        tenancy()->end();

        $from = now()->subDays(30)->toDateString();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled?published_from='.$from)
            ->assertOk()
            ->assertJsonPath('data.documents.total', 1)
            ->assertJsonPath('data.documents.data.0.document_code', 'ATC-NEW-P-001');
    }

    public function test_overview_requires_register_access(): void
    {
        tenancy()->initialize($this->testTenant);
        $user = TenantUser::query()->create([
            'name' => 'No Docs',
            'email' => 'no.docs@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        tenancy()->end();

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled/overview')
            ->assertForbidden();
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
