<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\EntraGraphAppService;
use App\Modules\Identity\Services\EntraOrgDirectoryService;
use App\Modules\Identity\Support\EntraDirectoryPerson;
use App\Modules\Identity\Support\EntraManagerLookupResult;
use App\Modules\Identity\Support\EntraUserManagerMatch;
use Mockery;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EntraOrgDirectoryServiceTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootInMemoryTenantApi();
    }

    public function test_org_chart_links_toweros_manager(): void
    {
        tenancy()->initialize($this->testTenant);

        $manager = TenantUser::query()->create([
            'name' => 'Alvin Tolentino',
            'email' => 'alvin@example.com',
            'password' => 'password',
            'is_active' => true,
            'job_title' => 'Director',
            'entra_licensed' => true,
        ]);
        $report = TenantUser::query()->create([
            'name' => 'Terrence Galang',
            'email' => 'terrence@example.com',
            'password' => 'password',
            'is_active' => true,
            'job_title' => 'Engineer',
            'manager_id' => $manager->id,
            'entra_org_synced_at' => now(),
            'entra_licensed' => true,
        ]);

        $chart = app(EntraOrgDirectoryService::class)->orgChart();

        $this->assertNotNull($chart['synced_at']);
        $people = collect($chart['people']);
        $terrence = $people->firstWhere('email', 'terrence@example.com');
        $alvin = $people->firstWhere('email', 'alvin@example.com');

        $this->assertSame((string) $manager->id, $terrence['manager_id']);
        $this->assertNull($terrence['manager_name']);
        $this->assertSame(1, $alvin['direct_report_count']);
        $this->assertSame((string) $report->id, $terrence['id']);

        tenancy()->end();
    }

    public function test_org_chart_keeps_entra_manager_name_when_manager_is_not_a_user(): void
    {
        tenancy()->initialize($this->testTenant);

        TenantUser::query()->create([
            'name' => 'Terrence Galang',
            'email' => 'terrence@example.com',
            'password' => 'password',
            'is_active' => true,
            'entra_manager_name' => 'Alvin Tolentino',
            'entra_manager_email' => 'alvin@example.com',
            'entra_manager_licensed' => true,
            'entra_manager_license_label' => 'Business Standard',
        ]);

        $chart = app(EntraOrgDirectoryService::class)->orgChart();
        $terrence = collect($chart['people'])->firstWhere('email', 'terrence@example.com');

        $this->assertNull($terrence['manager_id']);
        $this->assertSame('Alvin Tolentino', $terrence['manager_name']);
        $this->assertSame('alvin@example.com', $terrence['manager_email']);
        $this->assertTrue($terrence['manager_licensed']);
        $this->assertSame('Business Standard', $terrence['manager_license_label']);

        tenancy()->end();
    }

    public function test_org_chart_hides_unlicensed_entra_only_manager(): void
    {
        tenancy()->initialize($this->testTenant);

        TenantUser::query()->create([
            'name' => 'Terrence Galang',
            'email' => 'terrence@example.com',
            'password' => 'password',
            'is_active' => true,
            'entra_licensed' => true,
            'entra_license_label' => 'E3',
            'entra_manager_name' => 'Alvin Tolentino',
            'entra_manager_email' => 'alvin@example.com',
            'entra_manager_licensed' => false,
        ]);

        $chart = app(EntraOrgDirectoryService::class)->orgChart();
        $terrence = collect($chart['people'])->firstWhere('email', 'terrence@example.com');

        $this->assertNull($terrence['manager_id']);
        $this->assertNull($terrence['manager_name']);
        $this->assertNull($terrence['manager_email']);
        $this->assertFalse($terrence['manager_licensed']);

        tenancy()->end();
    }

    public function test_org_chart_nests_entra_only_manager_under_their_workspace_manager(): void
    {
        tenancy()->initialize($this->testTenant);

        $katrina = TenantUser::query()->create([
            'name' => 'Katrina Gaw',
            'email' => 'kcgaw@example.com',
            'password' => 'password',
            'is_active' => true,
            'entra_licensed' => true,
            'entra_license_label' => 'Business Standard',
        ]);
        TenantUser::query()->create([
            'name' => 'Neslie Valdez',
            'email' => 'nvaldez@example.com',
            'password' => 'password',
            'is_active' => true,
            'entra_licensed' => true,
            'entra_license_label' => 'Business Standard',
            'entra_manager_name' => 'Tranquilino Sarmiento',
            'entra_manager_email' => 'tmsarmiento@example.com',
            'entra_manager_licensed' => true,
            'entra_manager_license_label' => 'Business Standard',
            'entra_manager_parent_id' => $katrina->id,
        ]);

        $chart = app(EntraOrgDirectoryService::class)->orgChart();
        $neslie = collect($chart['people'])->firstWhere('email', 'nvaldez@example.com');

        $this->assertNull($neslie['manager_id']);
        $this->assertSame('Tranquilino Sarmiento', $neslie['manager_name']);
        $this->assertSame((string) $katrina->id, $neslie['manager_parent_id']);

        tenancy()->end();
    }

    public function test_app_sync_links_manager_when_both_users_exist(): void
    {
        tenancy()->initialize($this->testTenant);

        $manager = TenantUser::query()->create([
            'name' => 'Alvin',
            'email' => 'alvin@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $report = TenantUser::query()->create([
            'name' => 'Terrence',
            'email' => 'terrence@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $alvin = new EntraDirectoryPerson('entra-alvin', 'alvin@example.com', 'Alvin Tolentino', 'Director');
        $terrence = new EntraDirectoryPerson('entra-terrence', 'terrence@example.com', 'Terrence Galang', 'Engineer');

        $graph = Mockery::mock(EntraGraphAppService::class);
        $graph->shouldReceive('isConfigured')->andReturn(true);
        $graph->shouldReceive('directoryIdentifier')->andReturn('11111111-1111-1111-1111-111111111111');
        $graph->shouldReceive('getAppAccessToken')->andReturn('token');
        $graph->shouldReceive('subscribedSkuMap')->andReturn([]);
        $graph->shouldReceive('fetchManagerPerson')->andReturn(null);
        $graph->shouldReceive('findUserWithManager')->andReturnUsing(function (string $token, string $email) use ($alvin, $terrence) {
            return match ($email) {
                'alvin@example.com' => new EntraUserManagerMatch($alvin, null),
                'terrence@example.com' => new EntraUserManagerMatch($terrence, $alvin),
                default => EntraManagerLookupResult::fail(EntraManagerLookupResult::CODE_USER_NOT_FOUND, 'missing'),
            };
        });
        $this->app->instance(EntraGraphAppService::class, $graph);

        $result = app(EntraOrgDirectoryService::class)->syncDirectoryFromApp();

        $this->assertTrue($result['ok']);
        $this->assertGreaterThanOrEqual(1, $result['managers_linked']);

        $report->refresh();
        $this->assertSame((string) $manager->id, (string) $report->manager_id);
        $this->assertSame('Alvin Tolentino', $report->entra_manager_name);
        $this->assertSame('Engineer', $report->job_title);

        tenancy()->end();
    }

    public function test_org_chart_excludes_unlicensed_users(): void
    {
        tenancy()->initialize($this->testTenant);

        TenantUser::query()->create([
            'name' => 'Licensed User',
            'email' => 'licensed@example.com',
            'password' => 'password',
            'is_active' => true,
            'entra_licensed' => true,
            'entra_license_label' => 'E3',
            'entra_license_names' => ['Microsoft 365 E3'],
        ]);
        TenantUser::query()->create([
            'name' => 'Unlicensed User',
            'email' => 'unlicensed@example.com',
            'password' => 'password',
            'is_active' => true,
            'entra_licensed' => false,
        ]);

        $chart = app(EntraOrgDirectoryService::class)->orgChart();
        $emails = collect($chart['people'])->pluck('email')->all();

        $this->assertContains('licensed@example.com', $emails);
        $this->assertNotContains('unlicensed@example.com', $emails);
        $licensed = collect($chart['people'])->firstWhere('email', 'licensed@example.com');
        $this->assertSame('E3', $licensed['license_label']);

        tenancy()->end();
    }

    public function test_org_chart_hides_synced_users_without_license_flag(): void
    {
        tenancy()->initialize($this->testTenant);

        TenantUser::query()->create([
            'name' => 'Never Synced',
            'email' => 'new@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        TenantUser::query()->create([
            'name' => 'Synced Before Licenses',
            'email' => 'oldsync@example.com',
            'password' => 'password',
            'is_active' => true,
            'entra_org_synced_at' => now(),
        ]);

        $chart = app(EntraOrgDirectoryService::class)->orgChart();
        $emails = collect($chart['people'])->pluck('email')->all();

        $this->assertNotContains('new@example.com', $emails);
        $this->assertNotContains('oldsync@example.com', $emails);

        tenancy()->end();
    }

    public function test_org_chart_shows_never_synced_users_before_first_org_sync(): void
    {
        tenancy()->initialize($this->testTenant);

        TenantUser::query()->create([
            'name' => 'Local Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $chart = app(EntraOrgDirectoryService::class)->orgChart();
        $emails = collect($chart['people'])->pluck('email')->all();

        $this->assertContains('admin@example.com', $emails);

        tenancy()->end();
    }

    public function test_app_sync_inherits_department_from_manager_when_report_has_none(): void
    {
        tenancy()->initialize($this->testTenant);

        $manager = TenantUser::query()->create([
            'name' => 'Alvin',
            'email' => 'alvin@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $report = TenantUser::query()->create([
            'name' => 'Terrence',
            'email' => 'terrence@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $alvin = new EntraDirectoryPerson(
            'entra-alvin',
            'alvin@example.com',
            'Alvin Tolentino',
            'Director',
            [],
            'Technology and Quality',
        );
        $terrence = new EntraDirectoryPerson(
            'entra-terrence',
            'terrence@example.com',
            'Terrence Galang',
            'Engineer',
            [],
            null,
        );

        $graph = Mockery::mock(EntraGraphAppService::class);
        $graph->shouldReceive('isConfigured')->andReturn(true);
        $graph->shouldReceive('directoryIdentifier')->andReturn('11111111-1111-1111-1111-111111111111');
        $graph->shouldReceive('getAppAccessToken')->andReturn('token');
        $graph->shouldReceive('subscribedSkuMap')->andReturn([]);
        $graph->shouldReceive('fetchManagerPerson')->andReturn(null);
        $graph->shouldReceive('findUserWithManager')->andReturnUsing(function (string $token, string $email) use ($alvin, $terrence) {
            return match ($email) {
                'alvin@example.com' => new EntraUserManagerMatch($alvin, null),
                'terrence@example.com' => new EntraUserManagerMatch($terrence, $alvin),
                default => EntraManagerLookupResult::fail(EntraManagerLookupResult::CODE_USER_NOT_FOUND, 'missing'),
            };
        });
        $this->app->instance(EntraGraphAppService::class, $graph);

        $result = app(EntraOrgDirectoryService::class)->syncDirectoryFromApp();

        $this->assertTrue($result['ok']);
        $report->refresh();
        $manager->refresh();
        $this->assertSame((string) $manager->id, (string) $report->manager_id);
        $this->assertSame('Technology and Quality', $manager->department);
        $this->assertSame('Technology and Quality', $report->department);
        $this->assertSame('Technology and Quality', $report->entra_manager_department);

        tenancy()->end();
    }

    public function test_app_sync_does_not_overwrite_report_department_from_entra(): void
    {
        tenancy()->initialize($this->testTenant);

        TenantUser::query()->create([
            'name' => 'Alvin',
            'email' => 'alvin@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $report = TenantUser::query()->create([
            'name' => 'Terrence',
            'email' => 'terrence@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $alvin = new EntraDirectoryPerson(
            'entra-alvin',
            'alvin@example.com',
            'Alvin Tolentino',
            'Director',
            [],
            'Technology and Quality',
        );
        $terrence = new EntraDirectoryPerson(
            'entra-terrence',
            'terrence@example.com',
            'Terrence Galang',
            'Engineer',
            [],
            'Field Operations',
        );

        $graph = Mockery::mock(EntraGraphAppService::class);
        $graph->shouldReceive('isConfigured')->andReturn(true);
        $graph->shouldReceive('directoryIdentifier')->andReturn('11111111-1111-1111-1111-111111111111');
        $graph->shouldReceive('getAppAccessToken')->andReturn('token');
        $graph->shouldReceive('subscribedSkuMap')->andReturn([]);
        $graph->shouldReceive('fetchManagerPerson')->andReturn(null);
        $graph->shouldReceive('findUserWithManager')->andReturnUsing(function (string $token, string $email) use ($alvin, $terrence) {
            return match ($email) {
                'alvin@example.com' => new EntraUserManagerMatch($alvin, null),
                'terrence@example.com' => new EntraUserManagerMatch($terrence, $alvin),
                default => EntraManagerLookupResult::fail(EntraManagerLookupResult::CODE_USER_NOT_FOUND, 'missing'),
            };
        });
        $this->app->instance(EntraGraphAppService::class, $graph);

        app(EntraOrgDirectoryService::class)->syncDirectoryFromApp();

        $report->refresh();
        $this->assertSame('Field Operations', $report->department);

        tenancy()->end();
    }

    public function test_app_sync_skips_duplicate_entra_id_and_clips_long_fields(): void
    {
        tenancy()->initialize($this->testTenant);

        $first = TenantUser::query()->create([
            'name' => 'First',
            'email' => 'first@example.com',
            'password' => 'password',
            'is_active' => true,
            'entra_id' => 'entra-shared',
        ]);
        $second = TenantUser::query()->create([
            'name' => 'Second',
            'email' => 'second@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $longTitle = str_repeat('Lead ', 50);
        $personA = new EntraDirectoryPerson('entra-shared', 'first@example.com', 'First');
        $personB = new EntraDirectoryPerson('entra-shared', 'second@example.com', 'Second', $longTitle);

        $graph = Mockery::mock(EntraGraphAppService::class);
        $graph->shouldReceive('isConfigured')->andReturn(true);
        $graph->shouldReceive('directoryIdentifier')->andReturn('11111111-1111-1111-1111-111111111111');
        $graph->shouldReceive('getAppAccessToken')->andReturn('token');
        $graph->shouldReceive('subscribedSkuMap')->andReturn([]);
        $graph->shouldReceive('fetchManagerPerson')->andReturn(null);
        $graph->shouldReceive('findUserWithManager')->andReturnUsing(function (string $token, string $email) use ($personA, $personB) {
            return match ($email) {
                'first@example.com' => new EntraUserManagerMatch($personA, null),
                'second@example.com' => new EntraUserManagerMatch($personB, null),
                default => EntraManagerLookupResult::fail(EntraManagerLookupResult::CODE_USER_NOT_FOUND, 'missing'),
            };
        });
        $this->app->instance(EntraGraphAppService::class, $graph);

        $result = app(EntraOrgDirectoryService::class)->syncDirectoryFromApp();

        $this->assertTrue($result['ok']);
        $first->refresh();
        $second->refresh();
        $this->assertSame('entra-shared', $first->entra_id);
        $this->assertNull($second->entra_id);
        $this->assertSame(180, strlen((string) $second->job_title));

        tenancy()->end();
    }
}
