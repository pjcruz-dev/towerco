<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use App\Modules\ProcurementOne\Support\ProcurementVendorAccreditationStatus;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementVendorTest extends TestCase
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

    public function test_vendor_form_schema_returns_published_vendor_registration_form(): void
    {
        $formId = $this->createVendorRegistrationForm();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/vendors/form-schema')
            ->assertOk()
            ->assertJsonPath('data.form.id', $formId)
            ->assertJsonPath('data.form.metadata.form_family', 'vendor_registration');
    }

    public function test_approved_vendor_registration_creates_accredited_procurement_vendor(): void
    {
        $formId = $this->createVendorRegistrationForm();
        $submissionId = $this->createVendorSubmission($formId, [
            'company_name' => 'Acme Telecom Supplies Inc.',
            'tax_id' => '123-456-789-000',
            'vendor_category' => 'Equipment',
            'contact_name' => 'Jane Vendor',
            'contact_email' => 'jane.vendor@acme.test',
            'contact_phone' => '+63 917 000 0001',
            'registered_address' => '123 Vendor Street, Makati',
            'services_offered' => 'Tower hardware and installation',
            'bank_name' => 'Vendor Bank',
            'bank_account_no' => '0011223344',
            'procurement_approver' => (string) $this->testTenantAdmin->id,
        ]);

        $this->approveSubmission($submissionId);

        tenancy()->initialize($this->testTenant);
        $vendor = ProcurementVendor::query()->where('company_name', 'Acme Telecom Supplies Inc.')->first();
        $this->assertNotNull($vendor);
        $this->assertSame(ProcurementVendorAccreditationStatus::ACCREDITED, $vendor->accreditation_status);
        $this->assertSame($submissionId, (string) $vendor->source_submission_id);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/vendors')
            ->assertOk()
            ->assertJsonPath('data.0.company_name', 'Acme Telecom Supplies Inc.')
            ->assertJsonPath('data.0.accreditation_status', ProcurementVendorAccreditationStatus::ACCREDITED);
    }

    public function test_block_policy_filters_non_accredited_vendors_from_lookup(): void
    {
        $this->seedVendorMasterRow('Accredited Vendor', 'ACC001', ProcurementVendorAccreditationStatus::ACCREDITED);
        $this->seedVendorMasterRow('Pending Vendor', 'PEND001', ProcurementVendorAccreditationStatus::PENDING);
        $this->setVendorAccreditationPolicy(true, 'block');

        $lookup = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/master-data/vendors');

        $lookup->assertOk();
        $codes = collect($lookup->json('data.options'))->pluck('code')->all();
        $this->assertContains('ACC001', $codes);
        $this->assertNotContains('PEND001', $codes);
    }

    public function test_block_policy_rejects_po_with_non_accredited_vendor(): void
    {
        $this->seedVendorMasterRow('Suspended Vendor', 'SUSP001', ProcurementVendorAccreditationStatus::SUSPENDED);
        $this->setVendorAccreditationPolicy(true, 'block');

        [$prSubmissionId, $poFormId] = $this->createApprovedPrAndPoForms();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'vendor' => 'SUSP001',
                    'total_amount' => '5000',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['values.vendor']);
    }

    public function test_vendor_index_requires_view_permission(): void
    {
        tenancy()->initialize($this->testTenant);
        $viewer = TenantUser::query()->create([
            'name' => 'Procurement Viewer',
            'email' => 'proc-viewer@test.localhost',
            'password' => 'password',
        ]);
        $viewer->assignRole('viewer');
        tenancy()->end();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/vendors')
            ->assertOk();
    }

    private function setVendorAccreditationPolicy(bool $enabled, string $mode): void
    {
        tenancy()->initialize($this->testTenant);
        app(ProcurementOneSettingsService::class)->setJson(
            ProcurementOneSettingsService::VENDOR_ACCREDITATION_POLICY,
            ['enabled' => $enabled, 'mode' => $mode],
        );
        tenancy()->end();
    }

    private function seedVendorMasterRow(string $companyName, string $vendorCode, string $accreditationStatus): void
    {
        tenancy()->initialize($this->testTenant);

        $set = EApprovalMasterDataSet::query()->firstOrCreate(
            ['key' => 'vendors'],
            ['name' => 'Vendors', 'status' => 'active'],
        );

        $row = EApprovalMasterDataRow::query()->create([
            'set_id' => $set->id,
            'code' => $vendorCode,
            'label' => $companyName,
            'data_json' => [
                'company_name' => $companyName,
                'tax_id' => $vendorCode,
                'vendor_category' => 'Services',
                'contact' => ['email' => 'vendor@example.test'],
            ],
            'is_active' => true,
        ]);

        ProcurementVendor::query()->create([
            'master_data_row_id' => (string) $row->id,
            'vendor_code' => $vendorCode,
            'company_name' => $companyName,
            'tax_id' => $vendorCode,
            'category' => 'Services',
            'schema_version' => 1,
            'contact_json' => ['email' => 'vendor@example.test'],
            'banking_json' => [],
            'address_json' => [],
            'profile_json' => [],
            'accreditation_status' => $accreditationStatus,
            'is_active' => true,
        ]);

        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createApprovedPrAndPoForms(): array
    {
        $prFormId = $this->createPublishedForm(
            'Purchase requisition',
            [
                ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'purchase_requisition'],
        );

        $prSubmissionId = $this->createSubmission($prFormId, [
            'estimated_total' => '10000',
        ]);
        $this->approveSubmission($prSubmissionId);

        $poFormId = $this->createPublishedForm(
            'Purchase order',
            [
                ['type' => 'select', 'name' => 'vendor', 'label' => 'Vendor', 'validation' => ['required' => true], 'options' => ['master_data_key' => 'vendors']],
                ['type' => 'currency', 'name' => 'total_amount', 'label' => 'PO total', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'purchase_order', 'parent_form_family' => 'purchase_requisition'],
        );

        return [$prSubmissionId, $poFormId];
    }

    private function createVendorRegistrationForm(): string
    {
        return $this->createPublishedForm(
            'Vendor registration',
            [
                ['type' => 'text', 'name' => 'company_name', 'label' => 'Company name', 'validation' => ['required' => true]],
                ['type' => 'text', 'name' => 'tax_id', 'label' => 'Tax ID', 'validation' => ['required' => true]],
                ['type' => 'text', 'name' => 'vendor_category', 'label' => 'Category', 'validation' => ['required' => true]],
                ['type' => 'text', 'name' => 'contact_name', 'label' => 'Contact name', 'validation' => ['required' => true]],
                ['type' => 'email', 'name' => 'contact_email', 'label' => 'Contact email', 'validation' => ['required' => true]],
                ['type' => 'phone', 'name' => 'contact_phone', 'label' => 'Contact phone', 'validation' => ['required' => true]],
                ['type' => 'textarea', 'name' => 'registered_address', 'label' => 'Address', 'validation' => ['required' => true]],
                ['type' => 'textarea', 'name' => 'services_offered', 'label' => 'Services', 'validation' => ['required' => true]],
                ['type' => 'text', 'name' => 'bank_name', 'label' => 'Bank', 'validation' => ['required' => true]],
                ['type' => 'text', 'name' => 'bank_account_no', 'label' => 'Account no.', 'validation' => ['required' => true]],
                ['type' => 'approver', 'name' => 'procurement_approver', 'label' => 'Procurement reviewer', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'vendor_registration'],
            [
                ['type' => 'field', 'approverId' => 'procurement_approver', 'step_order' => 1],
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $metadata
     * @param  list<array<string, mixed>>  $steps
     */
    private function createPublishedForm(
        string $name,
        array $fields,
        array $metadata = [],
        array $steps = [],
    ): string {
        if ($steps === []) {
            $steps = [
                ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
            ];
        }

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => $name,
                'description' => 'Procurement vendor test',
                'status' => 'published',
                'metadata_json' => $metadata,
                'fields' => $fields,
                'steps' => $steps,
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function createVendorSubmission(string $formId, array $values): string
    {
        return $this->createSubmission($formId, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function createSubmission(string $formId, array $values): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => $values,
            ]);

        $response->assertCreated();

        return (string) $response->json('data.id');
    }

    private function approveSubmission(string $submissionId): void
    {
        $inbox = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');

        $inbox->assertOk();

        $approvalId = collect($inbox->json('data'))
            ->firstWhere('submission_id', $submissionId)['id'] ?? $inbox->json('data.0.id');

        $this->assertNotEmpty($approvalId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();
    }
}
