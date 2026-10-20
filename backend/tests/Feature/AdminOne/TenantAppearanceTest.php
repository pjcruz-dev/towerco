<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantAppearanceTest extends TestCase
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

        config(['toweros.logging.audit_structured_enabled' => false]);

        Schema::connection('central')->table('tenants', function (Blueprint $table): void {
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
    }

    public function test_guest_cannot_read_appearance(): void
    {
        $this->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/appearance')
            ->assertUnauthorized();
    }

    public function test_viewer_cannot_update_appearance(): void
    {
        $viewer = $this->createViewer();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/admin/appearance', $this->payload())
            ->assertForbidden();
    }

    public function test_tenant_admin_saves_chrome_for_this_tenant_only(): void
    {
        $other = Tenant::withoutEvents(function (): Tenant {
            return Tenant::query()->create(['id' => (string) Str::uuid()]);
        });
        $other->theme_tokens = [
            'version' => 1,
            'logo_url' => null,
            'favicon_url' => null,
            'light' => [],
            'dark' => [],
            'chrome' => [
                'saved' => true,
                'primary_preset' => 'blue',
                'primary' => '#2563eb',
                'skin' => 'default',
                'layout' => 'vertical',
                'content' => 'compact',
            ],
        ];
        $other->save();

        $this->testTenant->theme_tokens = [
            'version' => 2,
            'logo_url' => 'https://cdn.example.com/logo.png',
            'favicon_url' => null,
            'light' => ['background' => '#ffffff'],
            'dark' => [],
        ];
        $this->testTenant->save();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/admin/appearance', [
                'primary_preset' => 'teal',
                'skin' => 'bordered',
                'layout' => 'horizontal',
                'content' => 'wide',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.chrome.saved', true)
            ->assertJsonPath('data.chrome.primary_preset', 'teal')
            ->assertJsonPath('data.chrome.primary', '#0d9488')
            ->assertJsonPath('data.chrome.skin', 'bordered')
            ->assertJsonPath('data.chrome.layout', 'horizontal')
            ->assertJsonPath('data.chrome.content', 'wide')
            ->assertJsonPath('data.light.primary', '#0d9488')
            ->assertJsonPath('data.light.background', '#ffffff')
            ->assertJsonPath('data.logo_url', 'https://cdn.example.com/logo.png');

        $other->refresh();
        $this->assertSame('blue', $other->theme_tokens['chrome']['primary_preset']);

        $this->assertDatabaseHas('platform_tenant_audit_logs', [
            'tenant_id' => $this->testTenant->id,
            'event_type' => 'tenant.appearance.updated',
            'actor_email' => 'admin@test.localhost',
        ], 'central');
    }

    public function test_custom_color_without_a_hex_is_rejected(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/admin/appearance', [
                'primary_preset' => 'custom',
                'skin' => 'default',
                'layout' => 'vertical',
                'content' => 'compact',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['primary']);
    }

    public function test_unknown_skin_is_rejected(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/admin/appearance', [
                'primary_preset' => 'teal',
                'skin' => 'glass',
                'layout' => 'vertical',
                'content' => 'compact',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['skin']);
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'primary_preset' => 'violet',
            'skin' => 'default',
            'layout' => 'vertical',
            'content' => 'compact',
        ];
    }

    private function createViewer(): TenantUser
    {
        tenancy()->initialize($this->testTenant);
        $user = TenantUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('viewer');
        tenancy()->end();

        return $user;
    }
}
