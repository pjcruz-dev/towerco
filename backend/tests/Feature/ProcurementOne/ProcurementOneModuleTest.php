<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProcurementOne\Events\ProcurementDocumentApproved;
use App\Modules\ProcurementOne\Services\ProcurementDocumentEventDispatcher;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Event;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementOneModuleTest extends TestCase
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
                'project_one',
                'e_approval',
                'procurement_one',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'professional';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_dashboard_returns_kpis(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'kpis',
                    'message',
                ],
            ]);
    }

    public function test_metadata_returns_catalogs(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/metadata');

        $response->assertOk()
            ->assertJsonPath('data.document_types.0.id', ProcurementDocumentType::PURCHASE_REQUISITION)
            ->assertJsonStructure([
                'data' => [
                    'document_types',
                    'status_catalogs',
                    'numbering_series',
                    'reset_rules',
                    'plan_features' => ['enabled', 'goods_receipt', 'advanced_numbering'],
                ],
            ]);
    }

    public function test_settings_snapshot_and_update(): void
    {
        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/settings');

        $show->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'module_message',
                    'vendor_accreditation_policy' => ['enabled', 'mode'],
                    'document_types',
                    'status_catalogs',
                    'numbering_series',
                ],
            ]);

        $update = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/procurement-one/settings', [
                'module_message' => 'Procurement workspace is being configured.',
                'status_catalogs' => [
                    ProcurementDocumentType::PURCHASE_REQUISITION => [
                        ['key' => 'draft', 'label' => 'Draft'],
                        ['key' => 'approved', 'label' => 'Approved', 'terminal' => true],
                    ],
                ],
            ]);

        $update->assertOk()
            ->assertJsonPath('data.module_message', 'Procurement workspace is being configured.')
            ->assertJsonPath(
                'data.status_catalogs.'.ProcurementDocumentType::PURCHASE_REQUISITION.'.0.label',
                'Draft',
            );
    }

    public function test_document_approved_event_is_dispatched(): void
    {
        Event::fake([ProcurementDocumentApproved::class]);

        tenancy()->initialize($this->testTenant);
        app(ProcurementDocumentEventDispatcher::class)->approved(
            ProcurementDocumentType::PURCHASE_ORDER,
            '00000000-0000-0000-0000-000000000099',
            'PO-2026-00001',
            (string) $this->testTenantAdmin->id,
            ['source' => 'test'],
        );
        tenancy()->end();

        Event::assertDispatched(ProcurementDocumentApproved::class, function (ProcurementDocumentApproved $event): bool {
            return $event->eventName() === 'procurement.document.approved'
                && $event->documentType === ProcurementDocumentType::PURCHASE_ORDER
                && $event->documentNo === 'PO-2026-00001';
        });
    }

    public function test_starter_plan_blocks_dashboard(): void
    {
        $this->testTenant->plan_tier = 'starter';
        $this->testTenant->save();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/dashboard')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['procurement_one']);
    }
}
