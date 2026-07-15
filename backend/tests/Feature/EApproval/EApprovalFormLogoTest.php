<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalForm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalFormLogoTest extends TestCase
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

    public function test_form_logo_upload_and_download(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Logo test form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);

        tenancy()->end();

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/e-approval/forms/'.$form->id.'/logo', [
                'file' => UploadedFile::fake()->image('brand.png', 120, 40),
            ]);

        $upload->assertOk();
        $upload->assertJsonPath('data.brand_logo_url', '/api/v1/e-approval/forms/'.$form->id.'/logo');

        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/forms/'.$form->id);

        $show->assertOk();
        $show->assertJsonPath('data.brand_logo_url', '/api/v1/e-approval/forms/'.$form->id.'/logo');

        $download = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/forms/'.$form->id.'/logo');

        $download->assertOk();
        $this->assertStringContainsString('image/', (string) $download->headers->get('Content-Type'));
    }
}
