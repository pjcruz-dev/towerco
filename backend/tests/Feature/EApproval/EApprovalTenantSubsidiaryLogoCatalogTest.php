<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use App\Modules\EApproval\Services\EApprovalSubsidiaryLogoCatalogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalTenantSubsidiaryLogoCatalogTest extends TestCase
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

    public function test_tenant_upload_is_inherited_by_new_form_layout(): void
    {
        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/e-approval/subsidiary-logos/ATC', [
                'file' => UploadedFile::fake()->image('atc.png', 120, 40),
            ]);

        $upload->assertOk();
        $upload->assertJsonPath('data.code', 'ATC');
        $upload->assertJsonPath('data.logo_url', '/api/v1/e-approval/subsidiary-logos/ATC');
        $this->assertContains('ADIC', $upload->json('data.subsidiary_codes'));

        tenancy()->initialize($this->testTenant);
        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'New form without logos',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);
        tenancy()->end();

        $layout = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/pdf-layout/'.$form->id);

        $layout->assertOk();
        $layout->assertJsonPath(
            'data.template.subsidiary_logos.ATC',
            '/api/v1/e-approval/subsidiary-logos/ATC',
        );
        $this->assertContains('ATC', $layout->json('data.template.subsidiary_codes'));
        $this->assertContains('ADIC', $layout->json('data.template.subsidiary_codes'));

        $stream = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/subsidiary-logos/ATC');

        $stream->assertOk();
        $this->assertStringContainsString('image/', (string) $stream->headers->get('Content-Type'));
    }

    public function test_form_override_wins_over_tenant_catalog(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/e-approval/subsidiary-logos/ATC', [
                'file' => UploadedFile::fake()->image('tenant-atc.png', 80, 40),
            ])
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Override form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/ATC', [
                'file' => UploadedFile::fake()->image('form-atc.png', 60, 30),
            ])
            ->assertOk();

        $layout = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/pdf-layout/'.$form->id);

        $layout->assertOk();
        $layout->assertJsonPath(
            'data.template.subsidiary_logos.ATC',
            '/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/ATC',
        );
    }

    public function test_soft_seed_copies_form_logos_into_empty_catalog(): void
    {
        tenancy()->initialize($this->testTenant);
        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Seed source',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/e-approval/forms/'.$form->id.'/subsidiary-logos/ADIC', [
                'file' => UploadedFile::fake()->image('adic.png', 40, 40),
            ])
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        app(EApprovalSettingsService::class)->delete(EApprovalSubsidiaryLogoCatalogService::SETTINGS_KEY);
        $catalog = app(EApprovalSubsidiaryLogoCatalogService::class);
        $raw = $catalog->raw();
        tenancy()->end();

        $this->assertArrayHasKey('ADIC', $raw['logos']);
        $this->assertContains('ATC', $raw['codes']);
        $this->assertContains('ADIC', $raw['codes']);
    }
}
