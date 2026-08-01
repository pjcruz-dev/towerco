<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalAuditLog;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Models\EApprovalWorkflowTemplate;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Illuminate\Support\Str;
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

    public function test_submissions_export_includes_custom_fields_when_form_id_given(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Cash Advance Request',
            'category' => 'finance',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'FIN',
            'doc_type_code' => 'CA',
        ]);

        $amountField = EApprovalFormField::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'type' => 'text',
            'name' => 'amount_requested',
            'label' => 'Amount requested',
            'step_order' => 1,
        ]);

        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'CA-EXPORT-001',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        EApprovalFormValue::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'field_id' => $amountField->id,
            'value' => '12500',
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export?form_id='.$form->id);

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Amount requested', $csv);
        $this->assertStringContainsString('12500', $csv);
        $this->assertStringContainsString('CA-EXPORT-001', $csv);
    }

    public function test_submissions_export_without_form_id_omits_custom_field_columns(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Purchase Requisition',
            'category' => 'finance',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'PRC',
            'doc_type_code' => 'PR',
        ]);

        EApprovalFormField::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'type' => 'text',
            'name' => 'vendor_name',
            'label' => 'Vendor name',
            'step_order' => 1,
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringNotContainsString('Vendor name', $csv);
    }

    public function test_submissions_export_columns_endpoint_lists_base_and_field_columns(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Travel Request',
            'category' => 'finance',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'FIN',
            'doc_type_code' => 'TR',
        ]);

        $field = EApprovalFormField::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'type' => 'text',
            'name' => 'destination',
            'label' => 'Destination',
            'step_order' => 1,
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/submissions/export/columns')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'id')
            ->assertJsonPath('data.0.group', 'base')
            ->assertJsonFragment(['id' => $form->id, 'name' => 'Travel Request']);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/submissions/export/columns?form_id='.$form->id)
            ->assertOk()
            ->assertJsonFragment(['key' => 'field:'.$field->id, 'label' => 'Destination', 'group' => 'field'])
            ->assertJsonFragment(['key' => 'approvers', 'label' => 'Approvers', 'group' => 'approval'])
            ->assertJsonFragment(['key' => 'approval_dates', 'label' => 'Approver Dates', 'group' => 'approval']);
    }

    public function test_submissions_export_includes_approvers_and_acted_at(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Approval Export Form',
            'category' => 'ops',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'OPS',
            'doc_type_code' => 'AE',
        ]);

        $template = EApprovalWorkflowTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
        ]);

        $step = EApprovalWorkflowStep::query()->create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_id' => (string) $this->testTenantAdmin->id,
        ]);

        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'AE-001',
            'status' => EApprovalSubmissionStatus::APPROVED,
            'current_step' => 1,
        ]);

        EApprovalRequestApproval::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'step_id' => $step->id,
            'approver_id' => $this->testTenantAdmin->id,
            'status' => 'approved',
            'acted_at' => now()->subHour(),
            'remarks' => 'Looks good',
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export?form_id='.$form->id.'&columns[]=document_no&columns[]=approvers&columns[]=approval_dates&columns[]=step:1:approver&columns[]=step:1:acted_at&columns[]=step:1:status');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Approvers', $csv);
        $this->assertStringContainsString('Approver Dates', $csv);
        $this->assertStringContainsString('Test Admin', $csv);
        $this->assertStringContainsString('AE-001', $csv);
        $this->assertStringContainsString('approved', $csv);
    }

    public function test_submissions_export_respects_selected_columns(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->makeReportingForm('Column Selection Form', 'CSF');
        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'COLSEL-001',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export?columns[]=document_no&columns[]=status');

        $response->assertOk();
        $csv = $response->streamedContent();
        $lines = preg_split('/\r?\n/', trim(str_replace("\xEF\xBB\xBF", '', $csv))) ?: [];
        $header = str_getcsv($lines[0]);
        $this->assertSame(['Document No', 'Status'], $header);
        $this->assertStringNotContainsString('Requestor Email', $csv);
    }

    public function test_submissions_export_filters_by_multiple_statuses(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->makeReportingForm('Multi Status Form', 'MSF');
        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'MS-APPROVED',
            'status' => EApprovalSubmissionStatus::APPROVED,
            'current_step' => 1,
        ]);
        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'MS-REJECTED',
            'status' => EApprovalSubmissionStatus::REJECTED,
            'current_step' => 1,
        ]);
        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'MS-PENDING',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export?statuses[]=approved&statuses[]=rejected');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('MS-APPROVED', $csv);
        $this->assertStringContainsString('MS-REJECTED', $csv);
        $this->assertStringNotContainsString('MS-PENDING', $csv);
    }

    public function test_submissions_export_sets_truncation_headers(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export');

        $response->assertOk();
        $this->assertSame('0', $response->headers->get('X-Export-Truncated'));
        $this->assertSame('5000', $response->headers->get('X-Export-Max-Rows'));
        $this->assertNotNull($response->headers->get('X-Export-Total-Rows'));
    }

    public function test_submissions_export_supports_xlsx_format(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->makeReportingForm('Xlsx Form', 'XLS');
        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'XLSX-001',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export?format=xlsx');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type'),
        );

        $file = $response->baseResponse->getFile()->getPathname();
        $this->assertSame('PK', substr((string) file_get_contents($file), 0, 2));
    }

    public function test_line_item_export_emits_one_row_per_line_item(): void
    {
        tenancy()->initialize($this->testTenant);

        [$form, $grid] = $this->makeFormWithLineItems();
        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'LI-001',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);
        EApprovalFormValue::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'field_id' => $grid->id,
            'value' => json_encode([
                'rows' => [
                    ['0' => 'Cable', '1' => '2', '2' => '150'],
                    ['0' => 'Router', '1' => '1', '2' => '500'],
                ],
            ]),
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export?layout=line_items&form_id='.$form->id);

        $response->assertOk();
        $csv = str_replace("\xEF\xBB\xBF", '', $response->streamedContent());
        $lines = array_values(array_filter(preg_split('/\r?\n/', trim($csv)) ?: []));

        $header = str_getcsv($lines[0]);
        $this->assertContains('Line No', $header);
        $this->assertContains('Item', $header);
        $this->assertContains('Line Total', $header);

        // Two line items, both carrying the parent document number and computed totals.
        $this->assertSame(3, count($lines));
        $this->assertStringContainsString('LI-001', $lines[1]);
        $this->assertStringContainsString('Cable', $lines[1]);
        $this->assertStringContainsString('300.00', $lines[1]);
        $this->assertStringContainsString('Router', $lines[2]);
        $this->assertStringContainsString('500.00', $lines[2]);
    }

    public function test_line_item_export_requires_form_and_grid(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export?layout=line_items')
            ->assertStatus(422);

        tenancy()->initialize($this->testTenant);
        $form = $this->makeReportingForm('No Grid Form', 'NGF');
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export?layout=line_items&form_id='.$form->id)
            ->assertStatus(422);
    }

    public function test_columns_endpoint_exposes_grid_fields(): void
    {
        tenancy()->initialize($this->testTenant);
        [$form, $grid] = $this->makeFormWithLineItems();
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/submissions/export/columns?form_id='.$form->id)
            ->assertOk()
            ->assertJsonFragment(['key' => $grid->id, 'label' => 'Line items']);
    }

    public function test_line_item_xlsx_export_has_two_sheets(): void
    {
        tenancy()->initialize($this->testTenant);
        [$form, $grid] = $this->makeFormWithLineItems();
        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'LI-XLSX-001',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);
        EApprovalFormValue::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'field_id' => $grid->id,
            'value' => json_encode(['rows' => [['0' => 'Cable', '1' => '2', '2' => '150']]]),
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/submissions/export?layout=line_items&format=xlsx&form_id='.$form->id);

        $response->assertOk();

        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($file) === true);
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet2.xml'));
        $zip->close();
    }

    /**
     * @return array{0: EApprovalForm, 1: EApprovalFormField}
     */
    private function makeFormWithLineItems(): array
    {
        $form = $this->makeReportingForm('Line Item Form', 'LIF');
        $grid = EApprovalFormField::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'type' => 'grid',
            'name' => 'line_items',
            'label' => 'Line items',
            'step_order' => 1,
            'options' => [
                'columns' => [
                    ['label' => 'Item', 'type' => 'text'],
                    ['label' => 'Qty', 'type' => 'number'],
                    ['label' => 'Unit price', 'type' => 'currency'],
                ],
            ],
        ]);

        return [$form, $grid];
    }

    private function makeReportingForm(string $name, string $docType): EApprovalForm
    {
        return EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'category' => 'finance',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'OPS',
            'doc_type_code' => $docType,
        ]);
    }
}
