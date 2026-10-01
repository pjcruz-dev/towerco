<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Documents\Models\Site;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent demo users and site records for Document Control lease binders.
 */
class AllianceDemoSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantRbacBaselineService::class)->ensure();

        $this->seedUsers();
        $this->seedSites();

        if ($this->command !== null) {
            $this->command->info('Alliance demo data seeded (idempotent).');
        }
    }

    /**
     * @return array<string, TenantUser>
     */
    private function seedUsers(): array
    {
        $password = Hash::make('password');

        $definitions = [
            'manager' => [
                'name' => 'Operations Manager',
                'email' => 'manager@alliance.localhost',
                'roles' => ['manager'],
            ],
            'viewer' => [
                'name' => 'Workspace Viewer',
                'email' => 'ops.viewer@alliance.localhost',
                'roles' => ['viewer'],
            ],
            'pm' => [
                'name' => 'Project Lead',
                'email' => 'project.lead@alliance.localhost',
                'roles' => ['manager'],
            ],
            'finance' => [
                'name' => 'Finance Analyst',
                'email' => 'finance@alliance.localhost',
                'roles' => ['finance'],
            ],
        ];

        $users = [];
        foreach ($definitions as $key => $row) {
            /** @var TenantUser $user */
            $user = TenantUser::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => $password,
                ],
            );
            $user->syncRoles($row['roles']);
            $users[$key] = $user->fresh(['roles']);
        }

        return $users;
    }

    /**
     * @return array<string, Site>
     */
    private function seedSites(): array
    {
        $definitions = [
            'mnl' => [
                'site_code' => 'ALL-MNL-001',
                'name' => 'Makati Exchange',
                'latitude' => 14.554729,
                'longitude' => 121.024445,
                'type' => 'macro',
                'status' => 'active',
            ],
            'bgc' => [
                'site_code' => 'ALL-BGC-002',
                'name' => 'BGC Hub Tower',
                'latitude' => 14.551547,
                'longitude' => 121.046622,
                'type' => 'rooftop',
                'status' => 'active',
            ],
            'qc' => [
                'site_code' => 'ALL-QC-003',
                'name' => 'Quezon Central',
                'latitude' => 14.676041,
                'longitude' => 121.043701,
                'type' => 'macro',
                'status' => 'under_construction',
            ],
            'psg' => [
                'site_code' => 'ALL-PSG-004',
                'name' => 'Pasig Riverside',
                'latitude' => 14.576377,
                'longitude' => 121.085117,
                'type' => 'rooftop',
                'status' => 'active',
            ],
            'tag' => [
                'site_code' => 'ALL-TAG-005',
                'name' => 'Taguig Logistics Yard',
                'latitude' => 14.517635,
                'longitude' => 121.050895,
                'type' => 'warehouse',
                'status' => 'active',
            ],
            'ceb' => [
                'site_code' => 'ALL-CEB-006',
                'name' => 'Cebu North Ring',
                'latitude' => 10.315699,
                'longitude' => 123.885437,
                'type' => 'macro',
                'status' => 'active',
            ],
        ];

        $sites = [];
        foreach ($definitions as $key => $row) {
            $sites[$key] = Site::query()->updateOrCreate(
                ['site_code' => $row['site_code']],
                $row,
            );
        }

        return $sites;
    }
}
