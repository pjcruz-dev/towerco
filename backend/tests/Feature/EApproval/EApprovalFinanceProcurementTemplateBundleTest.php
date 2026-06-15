<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalForm;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalFinanceProcurementTemplateBundleTest extends TestCase
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

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();
    }

    public function test_finance_procurement_bundle_creates_six_forms_with_related_form_ids(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/form-templates/finance-procurement-bundle');

        $response->assertCreated();
        $response->assertJsonCount(6, 'data.forms');
        $response->assertJsonPath('data.bundle.id', 'finance_procurement');

        $formsByTemplate = collect($response->json('data.forms'))
            ->keyBy(static fn (array $form): string => (string) ($form['metadata_json']['created_from_template'] ?? ''));

        $this->assertCount(6, $formsByTemplate);

        $cashAdvanceId = (string) $formsByTemplate['cash_advance']['id'];
        $liquidationId = (string) $formsByTemplate['liquidation']['id'];
        $reimbursementId = (string) $formsByTemplate['reimbursement']['id'];
        $prId = (string) $formsByTemplate['purchase_requisition']['id'];
        $poId = (string) $formsByTemplate['purchase_order']['id'];
        $vendorId = (string) $formsByTemplate['vendor_registration']['id'];

        $this->assertEqualsCanonicalizing(
            [$liquidationId, $reimbursementId],
            $formsByTemplate['cash_advance']['related_form_ids'] ?? [],
        );
        $this->assertSame([$cashAdvanceId], $formsByTemplate['liquidation']['related_form_ids'] ?? []);
        $this->assertSame([], $formsByTemplate['reimbursement']['related_form_ids'] ?? []);
        $this->assertSame([$poId], $formsByTemplate['purchase_requisition']['related_form_ids'] ?? []);
        $this->assertEqualsCanonicalizing(
            [$prId, $vendorId],
            $formsByTemplate['purchase_order']['related_form_ids'] ?? [],
        );
        $this->assertSame([$poId], $formsByTemplate['vendor_registration']['related_form_ids'] ?? []);
    }

    public function test_bundle_reuses_existing_forms_and_rewires_related_form_ids(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/form-templates/finance-procurement-bundle')
            ->assertCreated();

        tenancy()->initialize($this->testTenant);
        $beforeCount = EApprovalForm::query()->count();
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/form-templates/finance-procurement-bundle');

        $response->assertCreated();
        $this->assertNotEmpty($response->json('data.warnings'));

        tenancy()->initialize($this->testTenant);
        $this->assertSame($beforeCount, EApprovalForm::query()->count());
        tenancy()->end();
    }

    public function test_single_template_create_resolves_related_form_ids_when_siblings_exist(): void
    {
        $cashAdvance = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/form-templates', [
                'template_id' => 'cash_advance',
            ])
            ->assertCreated()
            ->json('data.form');

        $liquidation = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/form-templates', [
                'template_id' => 'liquidation',
            ])
            ->assertCreated()
            ->json('data.form');

        $this->assertSame(
            [(string) $cashAdvance['id']],
            $liquidation['related_form_ids'] ?? [],
        );
    }
}
