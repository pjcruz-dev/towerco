<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalVendorRegistrationMasterDataTest extends TestCase
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

    public function test_approved_vendor_registration_creates_vendors_master_data_row(): void
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

        $lookup = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/master-data/vendors');

        $lookup->assertOk();
        $lookup->assertJsonPath('data.options.0.label', 'Acme Telecom Supplies Inc.');
        $lookup->assertJsonPath('data.options.0.code', '123456789000');
        $lookup->assertJsonPath('data.options.0.subtitle', 'Equipment · jane.vendor@acme.test · 123-456-789-000');
        $lookup->assertJsonPath('data.options.0.data.schema_version', 1);
        $lookup->assertJsonPath('data.options.0.data.contact.email', 'jane.vendor@acme.test');
        $lookup->assertJsonPath('data.options.0.data.banking.bank_name', 'Vendor Bank');
        $lookup->assertJsonPath('data.options.0.data.contact_email', 'jane.vendor@acme.test');
        $lookup->assertJsonPath('data.options.0.data.source.submission_id', $submissionId);
    }

    public function test_approved_vendor_registration_updates_existing_row_by_tax_id(): void
    {
        $formId = $this->createVendorRegistrationForm();

        $firstSubmissionId = $this->createVendorSubmission($formId, [
            'company_name' => 'Legacy Vendor Name',
            'tax_id' => 'TIN-998877',
            'vendor_category' => 'Services',
            'contact_name' => 'Old Contact',
            'contact_email' => 'old@vendor.test',
            'contact_phone' => '+63 917 000 0002',
            'registered_address' => 'Old address',
            'services_offered' => 'Old services',
            'bank_name' => 'Old Bank',
            'bank_account_no' => '1111',
            'procurement_approver' => (string) $this->testTenantAdmin->id,
        ]);
        $this->approveSubmission($firstSubmissionId);

        $secondSubmissionId = $this->createVendorSubmission($formId, [
            'company_name' => 'Updated Vendor Name',
            'tax_id' => 'TIN998877',
            'vendor_category' => 'Logistics',
            'contact_name' => 'New Contact',
            'contact_email' => 'new@vendor.test',
            'contact_phone' => '+63 917 000 0003',
            'registered_address' => 'New address',
            'services_offered' => 'Updated services',
            'bank_name' => 'New Bank',
            'bank_account_no' => '2222',
            'procurement_approver' => (string) $this->testTenantAdmin->id,
        ]);
        $this->approveSubmission($secondSubmissionId);

        tenancy()->initialize($this->testTenant);
        $set = EApprovalMasterDataSet::query()->where('key', 'vendors')->first();
        $this->assertNotNull($set);
        $this->assertSame(1, $set->rows()->count());
        tenancy()->end();

        $lookup = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/master-data/vendors');

        $lookup->assertOk();
        $lookup->assertJsonPath('data.options.0.label', 'Updated Vendor Name');
        $lookup->assertJsonPath('data.options.0.data.contact.email', 'new@vendor.test');
        $lookup->assertJsonPath('data.options.0.data.source.submission_id', $secondSubmissionId);
    }

    public function test_approved_vendor_registration_merges_manual_row_by_company_name(): void
    {
        $this->seedManualVendorRow(
            label: 'Legacy Vendor Co.',
            code: 'legacy-vendor',
            data: [
                'company_name' => 'Legacy Vendor Co.',
                'contact_email' => 'legacy@vendor.test',
            ],
        );

        $formId = $this->createVendorRegistrationForm();
        $submissionId = $this->createVendorSubmission($formId, [
            'company_name' => 'Legacy Vendor Company',
            'tax_id' => 'TIN-445566',
            'vendor_category' => 'Services',
            'contact_name' => 'Legacy Contact',
            'contact_email' => 'legacy@vendor.test',
            'contact_phone' => '+63 917 000 0099',
            'registered_address' => 'Legacy address',
            'services_offered' => 'Legacy services',
            'bank_name' => 'Legacy Bank',
            'bank_account_no' => '9999',
            'procurement_approver' => (string) $this->testTenantAdmin->id,
        ]);

        $this->approveSubmission($submissionId);

        tenancy()->initialize($this->testTenant);
        $set = EApprovalMasterDataSet::query()->where('key', 'vendors')->first();
        $this->assertNotNull($set);
        $this->assertSame(1, $set->rows()->count());
        $row = $set->rows()->first();
        $this->assertInstanceOf(EApprovalMasterDataRow::class, $row);
        $this->assertSame('TIN445566', $row->code);
        $this->assertSame('Legacy Vendor Company', $row->label);
        $this->assertSame('company_name', $row->data_json['dedupe']['matched_by'] ?? null);
        tenancy()->end();

        $lookup = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/master-data/vendors');

        $lookup->assertOk();
        $lookup->assertJsonCount(1, 'data.options');
        $lookup->assertJsonPath('data.options.0.code', 'TIN445566');
        $lookup->assertJsonPath('data.options.0.data.tax_id', 'TIN-445566');
    }

    public function test_conflicting_tax_ids_with_similar_company_names_create_separate_rows(): void
    {
        $formId = $this->createVendorRegistrationForm();

        $firstSubmissionId = $this->createVendorSubmission($formId, [
            'company_name' => 'Shared Services Corporation',
            'tax_id' => 'TIN-111111',
            'vendor_category' => 'Services',
            'contact_name' => 'Contact A',
            'contact_email' => 'a@shared.test',
            'contact_phone' => '+63 917 000 0101',
            'registered_address' => 'Address A',
            'services_offered' => 'Services A',
            'bank_name' => 'Bank A',
            'bank_account_no' => '1001',
            'procurement_approver' => (string) $this->testTenantAdmin->id,
        ]);
        $this->approveSubmission($firstSubmissionId);

        $secondSubmissionId = $this->createVendorSubmission($formId, [
            'company_name' => 'Shared Services Corp.',
            'tax_id' => 'TIN-222222',
            'vendor_category' => 'Logistics',
            'contact_name' => 'Contact B',
            'contact_email' => 'b@shared.test',
            'contact_phone' => '+63 917 000 0102',
            'registered_address' => 'Address B',
            'services_offered' => 'Services B',
            'bank_name' => 'Bank B',
            'bank_account_no' => '2002',
            'procurement_approver' => (string) $this->testTenantAdmin->id,
        ]);
        $this->approveSubmission($secondSubmissionId);

        tenancy()->initialize($this->testTenant);
        $set = EApprovalMasterDataSet::query()->where('key', 'vendors')->first();
        $this->assertNotNull($set);
        $this->assertSame(2, $set->rows()->count());
        tenancy()->end();

        $lookup = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/master-data/vendors');

        $lookup->assertOk();
        $lookup->assertJsonCount(2, 'data.options');
    }

    public function test_non_vendor_form_approval_does_not_create_vendor_row(): void
    {
        $formId = $this->createPublishedForm(
            'Generic request',
            [
                ['type' => 'text', 'name' => 'title', 'label' => 'Title', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'general'],
        );

        $submissionId = $this->createSubmission($formId, [
            'title' => 'Not a vendor',
        ]);
        $this->approveSubmission($submissionId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/master-data/vendors')
            ->assertStatus(422);
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
                'description' => 'Vendor master data test',
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
    private function createSubmission(string $formId, array $values, ?TenantUser $actor = null): string
    {
        $actor ??= $this->testTenantAdmin;

        $response = $this->actingAs($actor)
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => $values,
            ]);

        $response->assertCreated();

        return (string) $response->json('data.id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedManualVendorRow(string $label, string $code, array $data): void
    {
        tenancy()->initialize($this->testTenant);

        $set = EApprovalMasterDataSet::query()->firstOrCreate(
            ['key' => 'vendors'],
            ['name' => 'Vendors', 'status' => 'active'],
        );

        EApprovalMasterDataRow::query()->create([
            'set_id' => $set->id,
            'code' => $code,
            'label' => $label,
            'data_json' => $data,
            'is_active' => true,
        ]);

        tenancy()->end();
    }

    private function approveSubmission(string $submissionId, ?TenantUser $approver = null): void
    {
        $approver ??= $this->testTenantAdmin;

        $inbox = $this->actingAs($approver)
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');

        $inbox->assertOk();

        $approvalId = collect($inbox->json('data'))
            ->firstWhere('submission_id', $submissionId)['id'] ?? $inbox->json('data.0.id');

        $this->assertNotEmpty($approvalId);

        $this->actingAs($approver)
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();
    }
}
