<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Models\Tenant;
use App\Modules\AiAssistant\Models\AiAssistantSetting;
use App\Modules\AiAssistant\Services\TenantLlmCredentialService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\Workspace\Models\TenantActivityLog;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantLlmCredentialTest extends TestCase
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
                'ai_assistant',
            ],
            'ai_assistant.enabled' => true,
            'ai_assistant.llm_provider' => 'gemini',
            'ai_assistant.cursor.api_key' => 'env-cursor-key',
            'ai_assistant.gemini.api_key' => 'env-gemini-key',
            'ai_assistant.openai.api_key' => '',
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_admin_can_save_a_provider_token_and_copy_it_back(): void
    {
        $token = 'cursor_live_token_value_123456';

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/assistant/settings/llm', [
                'provider' => 'cursor',
                'api_key' => $token,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'cursor')
            ->assertJsonPath('data.provider_source', 'tenant')
            ->assertJsonPath('data.providers.0.id', 'cursor')
            ->assertJsonPath('data.providers.0.source', 'tenant')
            ->assertJsonPath('data.providers.0.api_key', $token)
            ->assertJsonPath('data.providers.1.id', 'gemini')
            ->assertJsonPath('data.providers.1.source', 'environment')
            ->assertJsonPath('data.providers.1.api_key', 'env-gemini-key');

        tenancy()->initialize($this->testTenant);
        $row = AiAssistantSetting::query()->findOrFail(AiAssistantSetting::SINGLETON_ID);
        $this->assertSame($token, $row->cursor_api_key);
        $this->assertNotSame($token, $row->getRawOriginal('cursor_api_key'));
        $service = app(TenantLlmCredentialService::class);
        $this->assertSame('cursor', $service->effectiveProvider());
        $this->assertSame($token, $service->apiKeyFor('cursor'));
        $this->assertSame('env-gemini-key', $service->apiKeyFor('gemini'));

        $audit = TenantActivityLog::query()->where('action', 'llm_credential.updated')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString($token, (string) json_encode($audit->metadata_json));
        tenancy()->end();
    }

    public function test_each_provider_keeps_its_own_token(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/assistant/settings/llm', [
                'provider' => 'cursor',
                'api_key' => 'cursor_workspace_token',
            ])
            ->assertOk();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/assistant/settings/llm', [
                'provider' => 'openai',
                'api_key' => 'openai_workspace_token',
            ])
            ->assertOk()
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.providers.2.source', 'tenant');

        tenancy()->initialize($this->testTenant);
        $row = AiAssistantSetting::query()->findOrFail(1);
        $this->assertSame('cursor_workspace_token', $row->cursor_api_key);
        $this->assertSame('openai_workspace_token', $row->openai_api_key);
        $this->assertSame('openai', app(TenantLlmCredentialService::class)->effectiveProvider());
        tenancy()->end();
    }

    public function test_blank_token_keeps_the_saved_token_and_can_switch_provider(): void
    {
        tenancy()->initialize($this->testTenant);
        AiAssistantSetting::query()->create([
            'id' => AiAssistantSetting::SINGLETON_ID,
            'llm_provider' => 'cursor',
            'cursor_api_key' => 'cursor_existing_token',
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/assistant/settings/llm', [
                'provider' => 'gemini',
            ])
            ->assertOk()
            ->assertJsonPath('data.provider', 'gemini')
            ->assertJsonPath('data.providers.0.source', 'tenant');

        tenancy()->initialize($this->testTenant);
        $this->assertSame('cursor_existing_token', AiAssistantSetting::query()->findOrFail(1)->cursor_api_key);
        tenancy()->end();
    }

    public function test_clear_falls_back_to_the_environment_key(): void
    {
        tenancy()->initialize($this->testTenant);
        AiAssistantSetting::query()->create([
            'id' => AiAssistantSetting::SINGLETON_ID,
            'llm_provider' => 'cursor',
            'cursor_api_key' => 'cursor_existing_token',
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/assistant/settings/llm', [
                'provider' => 'cursor',
                'clear' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.providers.0.source', 'environment')
            ->assertJsonPath('data.providers.0.configured', true)
            ->assertJsonPath('data.providers.0.api_key', 'env-cursor-key');

        tenancy()->initialize($this->testTenant);
        $this->assertNull(AiAssistantSetting::query()->findOrFail(1)->cursor_api_key);
        $this->assertSame('env-cursor-key', app(TenantLlmCredentialService::class)->apiKeyFor('cursor'));
        tenancy()->end();
    }

    public function test_show_requires_authentication(): void
    {
        $this->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assistant/settings/llm')
            ->assertUnauthorized();
    }

    public function test_update_is_forbidden_without_tenant_manage(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $user = TenantUser::query()->create([
            'name' => 'Billing User',
            'email' => 'billing@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('billing');
        tenancy()->end();

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/assistant/settings/llm', [
                'provider' => 'cursor',
                'api_key' => 'cursor_should_not_save',
            ])
            ->assertForbidden();
    }

    public function test_update_rejects_an_unknown_provider_and_a_non_string_token(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/assistant/settings/llm', [
                'provider' => 'local',
                'api_key' => ['not-a-token'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['provider', 'api_key']);
    }

    public function test_another_tenant_cannot_read_this_workspace_token(): void
    {
        $token = 'gemini_private_to_tenant_a';

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/assistant/settings/llm', [
                'provider' => 'gemini',
                'api_key' => $token,
            ])
            ->assertOk();

        $other = Tenant::withoutEvents(function (): Tenant {
            $tenant = Tenant::query()->create(['id' => (string) Str::uuid()]);
            $tenant->domains()->create(['domain' => 'other.localhost']);

            return $tenant;
        });
        (new CreateDatabase($other))->handle(app(DatabaseManager::class));
        (new MigrateDatabase($other))->handle();

        tenancy()->initialize($other);
        app(TenantRbacBaselineService::class)->ensure();
        $otherAdmin = TenantUser::query()->create([
            'name' => 'Other Admin',
            'email' => 'admin@other.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $otherAdmin->assignRole('tenant_admin');
        $this->assertNull(AiAssistantSetting::query()->find(AiAssistantSetting::SINGLETON_ID));
        $this->assertSame('env-gemini-key', app(TenantLlmCredentialService::class)->apiKeyFor('gemini'));
        tenancy()->end();

        $response = $this->actingAs($otherAdmin, 'sanctum')
            ->withHeaders(['X-Tenant-Id' => $other->id])
            ->getJson('/api/v1/assistant/settings/llm');

        $response->assertOk()
            ->assertJsonPath('data.provider', 'gemini')
            ->assertJsonPath('data.provider_source', 'environment');
        $this->assertStringNotContainsString($token, $response->getContent());

        tenancy()->initialize($this->testTenant);
        $this->assertSame($token, AiAssistantSetting::query()->findOrFail(1)->gemini_api_key);
        tenancy()->end();
    }
}
