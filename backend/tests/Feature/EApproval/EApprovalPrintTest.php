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
