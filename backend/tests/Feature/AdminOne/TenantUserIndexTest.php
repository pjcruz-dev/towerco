<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantUserIndexTest extends TestCase
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

    public function test_user_index_includes_security_summary_fields(): void
    {
        $target = $this->createTenantUser('ops.user@towerone.test', 'Ops User');
        $this->seedSession($target, 'azure_sso', now()->subDay());

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?search=ops.user@towerone.test');

        $response->assertOk()
            ->assertJsonPath('data.0.id', (string) $target->id)
            ->assertJsonPath('data.0.auth_methods.0', 'azure_sso')
            ->assertJsonPath('data.0.mfa_enrolled', false)
            ->assertJsonPath('data.0.mfa_required', false);

        $this->assertNotNull($response->json('data.0.last_active_at'));
    }

    public function test_user_index_filters_never_active_users(): void
    {
        $active = $this->createTenantUser('active.user@towerone.test', 'Active User');
        $inactive = $this->createTenantUser('never.user@towerone.test', 'Never User');
        $this->seedSession($active, 'local', now()->subHours(2));

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?last_active=never&per_page=50');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $inactive->id, $ids);
        $this->assertNotContains((string) $active->id, $ids);
    }

    public function test_user_index_filters_mfa_not_enrolled_users(): void
    {
        $plain = $this->createTenantUser('plain.user@towerone.test', 'Plain User');
        $enrolled = $this->createTenantUser('mfa.user@towerone.test', 'MFA User');
        $this->seedMfaFactor($enrolled);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?mfa=not_enrolled&per_page=50');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $plain->id, $ids);
        $this->assertNotContains((string) $enrolled->id, $ids);
    }

    public function test_user_ids_endpoint_returns_matching_ids(): void
    {
        $first = $this->createTenantUser('ids.one@towerone.test', 'Ids One');
        $second = $this->createTenantUser('ids.two@towerone.test', 'Ids Two');
        $this->createTenantUser('other@towerone.test', 'Other User');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users/ids?search=ids.');

        $response->assertOk()
            ->assertJsonPath('data.truncated', false);

        $ids = $response->json('data.ids');
        $this->assertIsArray($ids);
        $this->assertContains((string) $first->id, $ids);
        $this->assertContains((string) $second->id, $ids);
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_user_index_falls_back_department_display_to_manager(): void
    {
        tenancy()->initialize($this->testTenant);

        if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'department')
            || ! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'manager_id')) {
            tenancy()->end();
            $this->markTestSkipped('Org department columns not present in test tenant schema.');
        }

        $manager = TenantUser::query()->create([
            'name' => 'Dept Manager',
            'email' => 'dept.manager@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => 'Supply Chain Management',
        ]);
        $manager->assignRole('viewer');

        $report = TenantUser::query()->create([
            'name' => 'Dept Report',
            'email' => 'dept.report@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => null,
            'manager_id' => $manager->id,
        ]);
        $report->assignRole('viewer');
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?search=dept.report@towerone.test');

        $response->assertOk()
            ->assertJsonPath('data.0.id', (string) $report->id)
            ->assertJsonPath('data.0.department', null)
            ->assertJsonPath('data.0.department_display', 'Supply Chain Management')
            ->assertJsonPath('data.0.department_inherited', true)
            ->assertJsonPath('data.0.manager.department', 'Supply Chain Management');
    }

    public function test_user_index_no_department_filter_excludes_inherited_display(): void
    {
        tenancy()->initialize($this->testTenant);

        if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'department')
            || ! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'manager_id')) {
            tenancy()->end();
            $this->markTestSkipped('Org department columns not present in test tenant schema.');
        }

        $manager = TenantUser::query()->create([
            'name' => 'Filter Manager',
            'email' => 'filter.manager@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => 'Operations and Maintenance',
        ]);
        $manager->assignRole('viewer');

        $inherited = TenantUser::query()->create([
            'name' => 'Inherited Dept User',
            'email' => 'filter.inherited@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => null,
            'manager_id' => $manager->id,
        ]);
        $inherited->assignRole('viewer');

        $blank = TenantUser::query()->create([
            'name' => 'Blank Dept User',
            'email' => 'filter.blank@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => null,
            'manager_id' => null,
        ]);
        $blank->assignRole('viewer');
        tenancy()->end();

        $none = rawurlencode('__none__');
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/admin/users?department={$none}&per_page=50");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $blank->id, $ids);
        $this->assertNotContains((string) $inherited->id, $ids);
        $this->assertNotContains((string) $manager->id, $ids);
    }

    public function test_user_index_department_filter_includes_inherited_display(): void
    {
        tenancy()->initialize($this->testTenant);

        if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'department')
            || ! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'manager_id')) {
            tenancy()->end();
            $this->markTestSkipped('Org department columns not present in test tenant schema.');
        }

        $manager = TenantUser::query()->create([
            'name' => 'Named Manager',
            'email' => 'named.manager@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => 'Finance and Accounting',
        ]);
        $manager->assignRole('viewer');

        $inherited = TenantUser::query()->create([
            'name' => 'Named Inherited',
            'email' => 'named.inherited@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => null,
            'manager_id' => $manager->id,
        ]);
        $inherited->assignRole('viewer');
        tenancy()->end();

        $dept = rawurlencode('Finance and Accounting');
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/admin/users?department={$dept}&per_page=50");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $manager->id, $ids);
        $this->assertContains((string) $inherited->id, $ids);
    }

    public function test_user_index_search_matches_department_and_job_title(): void
    {
        tenancy()->initialize($this->testTenant);

        if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'department')
            || ! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'job_title')) {
            tenancy()->end();
            $this->markTestSkipped('Org columns not present in test tenant schema.');
        }

        $byDept = TenantUser::query()->create([
            'name' => 'Search By Dept',
            'email' => 'search.dept@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => 'UniqueDeptZebra',
            'job_title' => 'Analyst',
        ]);
        $byDept->assignRole('viewer');

        $byTitle = TenantUser::query()->create([
            'name' => 'Search By Title',
            'email' => 'search.title@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => null,
            'job_title' => 'UniqueTitleQuokka',
        ]);
        $byTitle->assignRole('viewer');
        tenancy()->end();

        $deptResponse = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?search=UniqueDeptZebra');
        $deptResponse->assertOk()
            ->assertJsonPath('data.0.id', (string) $byDept->id);

        $titleResponse = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?search=UniqueTitleQuokka');
        $titleResponse->assertOk()
            ->assertJsonPath('data.0.id', (string) $byTitle->id);
    }

    private function createTenantUser(string $email, string $name): TenantUser
    {
        tenancy()->initialize($this->testTenant);
        $user = TenantUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('viewer');
        tenancy()->end();

        return $user;
    }

    private function seedSession(TenantUser $user, string $authMethod, \DateTimeInterface $lastSeen): void
    {
        tenancy()->initialize($this->testTenant);
        DB::table('auth_sessions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'auth_method' => $authMethod,
            'state' => 'active',
            'last_seen_at' => $lastSeen,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();
    }

    private function seedMfaFactor(TenantUser $user): void
    {
        tenancy()->initialize($this->testTenant);
        DB::table('mfa_factors')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'totp',
            'secret_encrypted' => 'encrypted-secret',
            'is_primary' => true,
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();
    }
}
