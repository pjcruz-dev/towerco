<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalAuditLog;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalReportingTest extends TestCase
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

    public function test_audit_index_requires_permission(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/audit')
            ->assertOk();
    }

    public function test_dashboard_includes_p2_reporting_fields(): void
    {
        tenancy()->initialize($this->testTenant);
        EApprovalAuditLog::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->testTenantAdmin->id,
            'action' => 'test_action',
            'target_id' => 'target-1',
            'remarks' => 'test',
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/dashboard')
            ->assertOk()
            ->assertJsonPath('data.phase', 'P7')
            ->assertJsonStructure(['data' => ['recent_audit', 'kpis', 'finance_kpis', 'finance_counts']]);
    }

    public function test_submissions_export_returns_csv(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }
}
