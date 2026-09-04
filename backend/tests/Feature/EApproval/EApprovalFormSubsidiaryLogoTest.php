<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalFormSubsidiaryLogoTest extends TestCase
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
        Storage::fake('tenant_files');
        config(['toweros.tenant_files.disk' => 'tenant_files']);
        config(['filesystems.disks.tenant_files' => [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/tenant_files'),
            'serve' => false,
            'throw' => false,
        ]]);
    }

    public function test_subsidiary_logo_upload_show_and_clear(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Subsidiary logo form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);

        tenancy()->end();

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/ATC', [
                'file' => UploadedFile::fake()->image('atc.png', 120, 40),
            ]);

        $upload->assertOk();
        $upload->assertJsonPath('data.code', 'ATC');
        $upload->assertJsonPath(
            'data.logo_url',
            '/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/ATC',
        );
        $upload->assertJsonPath(
            'data.subsidiary_logos.ATC',
            '/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/ATC',
        );
        $upload->assertJsonPath('data.subsidiary_codes.0', 'ATC');

        $layout = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/pdf-layout/'.$form->id);

        $layout->assertOk();
        $layout->assertJsonPath(
            'data.template.subsidiary_logos.ATC',
            '/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/ATC',
        );

        $download = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/ATC');

        $download->assertOk();
        $this->assertStringContainsString('image/', (string) $download->headers->get('Content-Type'));

        $clear = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson('/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/ATC');

        $clear->assertOk();
        $clear->assertJsonMissingPath('data.subsidiary_logos.ATC');
        // Clearing logo keeps the code registered.
        $clear->assertJsonPath('data.subsidiary_codes.0', 'ATC');
    }

    public function test_accepts_custom_subsidiary_code_and_syncs_select_choices(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Custom code form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);

        EApprovalFormField::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'name' => 'subsidiary',
            'label' => 'Subsidiary',
            'type' => 'select',
            'step_order' => 1,
            'options' => [
                'choices' => [
                    ['value' => 'ATC', 'label' => 'ATC'],
                    ['value' => 'ADIC', 'label' => 'ADIC'],
                ],
            ],
        ]);

        tenancy()->end();

        $register = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms/'.$form->id.'/subsidiary-codes', [
                'code' => 'newco',
            ]);

        $register->assertOk();
        $register->assertJsonPath('data.code', 'NEWCO');
        $this->assertContains('NEWCO', $register->json('data.subsidiary_codes'));

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/NEWCO', [
                'file' => UploadedFile::fake()->image('newco.png', 40, 40),
            ]);

        $upload->assertOk();
        $upload->assertJsonPath('data.code', 'NEWCO');

        tenancy()->initialize($this->testTenant);
        $field = EApprovalFormField::query()
            ->where('form_id', $form->id)
            ->where('name', 'subsidiary')
            ->first();
        $this->assertNotNull($field);
        $choices = $field->options['choices'] ?? [];
        $values = array_column($choices, 'value');
        $this->assertContains('NEWCO', $values);
        tenancy()->end();

        $remove = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson('/api/v1/e-approval/forms/'.$form->id.'/subsidiary-codes/NEWCO');

        $remove->assertOk();
        $this->assertNotContains('NEWCO', $remove->json('data.subsidiary_codes') ?? []);
        $remove->assertJsonMissingPath('data.subsidiary_logos.NEWCO');
    }

    public function test_rejects_invalid_subsidiary_code(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Bad code form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);

        tenancy()->end();

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/bad code!', [
                'file' => UploadedFile::fake()->image('x.png', 40, 40),
            ]);

        $upload->assertNotFound();

        $register = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms/'.$form->id.'/subsidiary-codes', [
                'code' => 'bad code!',
            ]);

        $register->assertStatus(422);
    }
}
