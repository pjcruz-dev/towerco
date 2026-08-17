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
        ]);
        $report = TenantUser::query()->create([
            'name' => 'Terrence Galang',
            'email' => 'terrence@example.com',
            'password' => 'password',
            'is_active' => true,
            'job_title' => 'Engineer',
            'manager_id' => $manager->id,
            'entra_org_synced_at' => now(),
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
        ]);

        $chart = app(EntraOrgDirectoryService::class)->orgChart();
        $terrence = collect($chart['people'])->firstWhere('email', 'terrence@example.com');

        $this->assertNull($terrence['manager_id']);
        $this->assertSame('Alvin Tolentino', $terrence['manager_name']);
        $this->assertSame('alvin@example.com', $terrence['manager_email']);

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
}
