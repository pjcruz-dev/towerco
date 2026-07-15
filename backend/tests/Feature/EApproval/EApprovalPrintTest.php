<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalPrintTest extends TestCase
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

    public function test_print_data_respects_saved_layout_visibility(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Print Test Form',
            'status' => 'published',
        ]);

        $visibleField = EApprovalFormField::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'type' => 'text',
            'name' => 'visible_field',
            'label' => 'Visible',
        ]);

        EApprovalFormField::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'type' => 'text',
            'name' => 'hidden_field',
            'label' => 'Hidden',
        ]);

        app(EApprovalSettingsService::class)->setJson('pdf_layout_form_'.$form->id, [
            'layout' => [
                ['key' => 'visible_field', 'label' => 'Visible', 'visible' => true, 'fieldType' => 'text'],
                ['key' => 'hidden_field', 'label' => 'Hidden', 'visible' => false, 'fieldType' => 'text'],
            ],
            'template' => ['header' => ['title' => 'Test']],
            'active_preset_id' => 'default',
        ]);

        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => 'TEST-00001',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => 'approved',
            'current_step' => 1,
        ]);

        EApprovalFormValue::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'field_id' => $visibleField->id,
            'value' => 'show me',
        ]);

        EApprovalFormValue::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'field_id' => EApprovalFormField::query()->where('name', 'hidden_field')->value('id'),
            'value' => 'hide me',
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submission->id}/print");

        $response->assertOk()
            ->assertJsonPath('data.document_no', 'TEST-00001')
            ->assertJsonCount(1, 'data.fields')
            ->assertJsonPath('data.fields.0.key', 'visible_field')
            ->assertJsonPath('data.fields.0.value', 'show me');
    }

    public function test_purchase_requisition_print_includes_all_fields_and_labeled_grid_rows(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Purchase requisition print',
            'status' => 'published',
            'metadata_json' => [
                'form_family' => 'purchase_requisition',
                'print_template_kind' => 'purchase_requisition',
            ],
        ]);

        $fieldDefs = [
            ['type' => 'text', 'name' => 'requisition_title', 'label' => 'Title'],
            ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'options' => ['choices' => [['value' => 'fin', 'label' => 'FIN']]]],
            ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal']]]],
            ['type' => 'select', 'name' => 'currency', 'label' => 'Currency', 'options' => ['choices' => [['value' => 'PHP', 'label' => 'PHP']]]],
            ['type' => 'date', 'name' => 'needed_by', 'label' => 'Needed by'],
            ['type' => 'grid', 'name' => 'line_items', 'label' => 'Line items', 'options' => ['columns' => [
                ['label' => 'Description', 'type' => 'text'],
                ['label' => 'Qty', 'type' => 'number'],
                ['label' => 'Unit price', 'type' => 'currency'],
                ['label' => 'Amount', 'type' => 'currency'],
            ]]],
            ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total'],
            ['type' => 'textarea', 'name' => 'justification', 'label' => 'Justification'],
        ];

        foreach ($fieldDefs as $index => $field) {
            EApprovalFormField::query()->create([
                'id' => (string) Str::uuid(),
                'form_id' => $form->id,
                'type' => $field['type'],
                'name' => $field['name'],
                'label' => $field['label'],
                'options' => $field['options'] ?? null,
                'step_order' => $index,
            ]);
        }

        $fieldIds = EApprovalFormField::query()
            ->where('form_id', $form->id)
            ->pluck('id', 'name')
            ->all();

        app(EApprovalSettingsService::class)->setJson('pdf_layout_form_'.$form->id, [
            'layout' => [
                ['key' => 'requisition_title', 'label' => 'Title', 'visible' => true, 'fieldType' => 'text'],
                ['key' => 'department', 'label' => 'Department', 'visible' => true, 'fieldType' => 'select'],
                ['key' => 'urgency', 'label' => 'Urgency', 'visible' => true, 'fieldType' => 'select'],
                ['key' => 'currency', 'label' => 'Currency', 'visible' => false, 'fieldType' => 'select'],
                ['key' => 'needed_by', 'label' => 'Needed by', 'visible' => false, 'fieldType' => 'date'],
                ['key' => 'estimated_total', 'label' => 'Estimated total', 'visible' => false, 'fieldType' => 'currency'],
            ],
            'template' => ['layout_kind' => 'purchase_requisition'],
            'active_preset_id' => 'default',
        ]);

        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => 'GEN-PR-00008',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
            'current_step' => 1,
        ]);

        $values = [
            'requisition_title' => 'Office supplies',
            'department' => 'fin',
            'urgency' => 'normal',
            'currency' => 'PHP',
            'needed_by' => '2026-07-15',
            'line_items' => json_encode([
                ['Description' => 'Paper', 'Qty' => '10', 'Unit price' => '250'],
            ], JSON_THROW_ON_ERROR),
            'estimated_total' => '2500.00',
            'justification' => 'Monthly replenishment.',
        ];

        foreach ($values as $name => $value) {
            EApprovalFormValue::query()->create([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'field_id' => $fieldIds[$name],
                'value' => $value,
            ]);
        }

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submission->id}/print");

        $response->assertOk()
            ->assertJsonPath('data.print_template_kind', 'purchase_requisition');

        $fieldKeys = collect($response->json('data.fields'))->pluck('key')->all();
        $this->assertContains('currency', $fieldKeys);
        $this->assertContains('needed_by', $fieldKeys);
        $this->assertContains('estimated_total', $fieldKeys);

        $fields = collect($response->json('data.fields'))->keyBy('key');
        $this->assertSame('PHP', $fields->get('currency')['value']);
        $this->assertSame('2026-07-15', $fields->get('needed_by')['value']);
        $this->assertSame('2500.00', $fields->get('estimated_total')['value']);

        $grid = collect($response->json('data.grids'))->firstWhere('key', 'line_items');
        $this->assertNotNull($grid);
        $this->assertSame(['Paper', '10', '250', '2500.00'], $grid['rows'][0]);
    }

    public function test_pdf_layout_save_requires_one_visible_field(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Layout Form',
            'status' => 'published',
        ]);

        EApprovalFormField::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'type' => 'text',
            'name' => 'only_field',
            'label' => 'Only',
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/pdf-layout/{$form->id}", [
                'layout' => [
                    ['key' => 'only_field', 'label' => 'Only', 'visible' => false, 'fieldType' => 'text'],
                ],
            ])
            ->assertStatus(422);
    }
}
