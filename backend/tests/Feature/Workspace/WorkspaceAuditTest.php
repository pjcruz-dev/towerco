<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Services\EApprovalAuditLogger;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class WorkspaceAuditTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'e_approval',
            ],
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_workspace_audit_requires_permission(): void
    {
        $blocked = $this->testTenantAdmin;
        tenancy()->initialize($this->testTenant);
        $blocked->syncPermissions(['dashboard:view']);
        $blocked->syncRoles([]);
        tenancy()->end();

        $this->actingAs($blocked, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/audit')
            ->assertForbidden();
    }

    public function test_workspace_audit_lists_dual_written_e_approval_events(): void
    {
        tenancy()->initialize($this->testTenant);
        app(EApprovalAuditLogger::class)->log(
            'submission_manual_follow_up',
            'sub-123',
            'Reminder sent',
            $this->testTenantAdmin,
        );
        $this->assertSame(1, \App\Modules\EApproval\Models\EApprovalAuditLog::query()->count());
        $this->assertSame(1, \App\Modules\Workspace\Models\TenantActivityLog::query()->count());
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/audit')
            ->assertOk()
            ->assertJsonFragment(['source' => 'workspace', 'action' => 'submission_manual_follow_up'])
            ->assertJsonFragment(['action_label' => 'Manual follow-up sent'])
            ->assertJsonMissing(['source' => 'e_approval']);
    }

    public function test_workspace_audit_includes_procurement_dual_write(): void
    {
        tenancy()->initialize($this->testTenant);
        app(\App\Modules\ProcurementOne\Services\ProcurementLifecycleAuditService::class)->record(
            \App\Modules\ProcurementOne\Support\ProcurementDocumentType::PURCHASE_REQUISITION,
            'pr-1',
            'PR-100',
            'cancelled',
            $this->testTenantAdmin,
            'No longer required',
            [
                'changes' => [
                    'status' => ['from' => 'pending_approval', 'to' => 'cancelled'],
                ],
            ],
        );
        $this->assertSame(1, \App\Modules\Workspace\Models\TenantActivityLog::query()->count());
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/audit?module=procurement_one')
            ->assertOk()
            ->assertJsonFragment([
                'module' => 'procurement_one',
                'action' => 'purchase_requisition.cancelled',
                'entity_label' => 'PR-100',
            ])
            ->assertJsonPath('data.0.changes.status.to', 'cancelled')
            ->assertJsonPath('data.0.category', 'lifecycle')
            ->assertJsonPath('data.0.severity', 'high')
            ->assertJsonPath('data.0.reason', 'No longer required');
    }

    public function test_workspace_audit_exposes_normalized_changes(): void
    {
        tenancy()->initialize($this->testTenant);
        app(EApprovalAuditLogger::class)->log(
            'submission_cancelled',
            'sub-changes',
            'DOC-CHANGE',
            $this->testTenantAdmin,
            [
                'status' => ['from' => 'pending', 'to' => 'cancelled'],
                'password' => ['from' => 'old', 'to' => 'new'],
            ],
            entityLabel: 'DOC-CHANGE',
        );
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/audit')
            ->assertOk()
            ->assertJsonFragment([
                'action' => 'submission_cancelled',
                'entity_label' => 'DOC-CHANGE',
            ])
            ->assertJsonPath('data.0.changes.status.from', 'pending')
            ->assertJsonPath('data.0.changes.status.to', 'cancelled')
            ->assertJsonMissing(['password' => ['from' => 'old', 'to' => 'new']]);
    }

    public function test_workspace_audit_filters_by_actor(): void
    {
        tenancy()->initialize($this->testTenant);
        app(EApprovalAuditLogger::class)->log(
            'submission_created',
            'sub-actor',
            'DOC-1',
            $this->testTenantAdmin,
        );
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/audit?actor='.rawurlencode((string) $this->testTenantAdmin->email))
            ->assertOk()
            ->assertJsonFragment(['action' => 'submission_created']);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/audit?actor=nobody-matching-xyz')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_workspace_audit_export_streams_csv(): void
    {
        tenancy()->initialize($this->testTenant);
        app(EApprovalAuditLogger::class)->log(
            'form_created',
            'form-1',
            'Site form',
            $this->testTenantAdmin,
        );
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/workspace/audit/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('action_label', $csv);
        $this->assertStringContainsString('form_created', $csv);
        $this->assertStringContainsString('Form created', $csv);
    }
}
