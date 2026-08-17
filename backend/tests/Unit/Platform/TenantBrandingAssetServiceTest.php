<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Models\Tenant;
use App\Modules\Platform\Services\PlatformTenantAuditLogger;
use App\Modules\Platform\Services\TenantBrandingAssetService;
use App\Modules\Platform\Support\StructuredAuditLogWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TenantBrandingAssetServiceTest extends TestCase
{
    private TenantBrandingAssetService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('tenancy.database.central_connection', 'central');

        DB::purge('central');
        DB::setDefaultConnection('central');

        Schema::connection('central')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->timestamps();
            $table->json('data')->nullable();
            $table->json('theme_tokens')->nullable();
        });

        Schema::connection('central')->create('platform_tenant_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('event_type', 64);
            $table->string('actor_user_id', 36)->nullable();
            $table->string('actor_email')->nullable();
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Storage::fake('local');
        Config::set('toweros.logging.audit_structured_enabled', false);

        $this->service = new TenantBrandingAssetService(
            new PlatformTenantAuditLogger(new StructuredAuditLogWriter),
        );
    }

    public function test_store_logo_persists_file_and_hosted_url(): void
    {
        $tenant = $this->makeTenant();

        $tokens = $this->service->store(
            $tenant,
            UploadedFile::fake()->image('brand.png', 64, 64),
            TenantBrandingAssetService::KIND_LOGO,
            null,
        );

        $expectedUrl = '/api/v1/public/tenant-branding/logo?tenant='.$tenant->id;
        $this->assertSame($expectedUrl, $tokens['logo_url']);
        $this->assertSame('platform/tenant-branding/'.$tenant->id.'/logo.png', $tokens['logo_asset']);
        Storage::disk('local')->assertExists($tokens['logo_asset']);

        $tenant->refresh();
        $this->assertSame($expectedUrl, $tenant->theme_tokens['logo_url'] ?? null);
    }

    public function test_rejects_svg_upload(): void
    {
        $tenant = $this->makeTenant();

        $this->expectException(ValidationException::class);

        $this->service->store(
            $tenant,
            UploadedFile::fake()->create('logo.svg', 20, 'image/svg+xml'),
            TenantBrandingAssetService::KIND_LOGO,
            null,
        );
    }

    public function test_saving_external_url_removes_uploaded_file(): void
    {
        $tenant = $this->makeTenant();
        $stored = $this->service->store(
            $tenant,
            UploadedFile::fake()->image('brand.png', 32, 32),
            TenantBrandingAssetService::KIND_LOGO,
            null,
        );
        $path = $stored['logo_asset'];
        Storage::disk('local')->assertExists($path);

        $tenant->refresh();
        $merged = $this->service->mergeForSave($tenant, [
            'version' => 3,
            'logo_url' => 'https://cdn.example.com/acme.png',
            'favicon_url' => null,
            'light' => [],
            'dark' => [],
        ]);

        $this->assertSame('https://cdn.example.com/acme.png', $merged['logo_url']);
        $this->assertArrayNotHasKey('logo_asset', $merged);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_saving_hosted_url_keeps_uploaded_file(): void
    {
        $tenant = $this->makeTenant();
        $stored = $this->service->store(
            $tenant,
            UploadedFile::fake()->image('brand.png', 32, 32),
            TenantBrandingAssetService::KIND_LOGO,
            null,
        );
        $path = $stored['logo_asset'];
        $tenant->refresh();

        $merged = $this->service->mergeForSave($tenant, [
            'version' => 4,
            'logo_url' => $this->service->hostedUrl($tenant, TenantBrandingAssetService::KIND_LOGO),
            'favicon_url' => null,
            'light' => [],
            'dark' => [],
        ]);

        $this->assertSame($path, $merged['logo_asset']);
        Storage::disk('local')->assertExists($path);
    }

    private function makeTenant(): Tenant
    {
        return Tenant::withoutEvents(function (): Tenant {
            return Tenant::query()->create(['id' => (string) Str::uuid()]);
        });
    }
}
