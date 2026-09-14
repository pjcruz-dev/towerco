<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Platform\Services\OperationalAcronymService;
use App\Modules\Platform\Support\OperationalAcronymDefaults;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Laravel\Passport\ClientRepository;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return;
        }

        $this->ensurePassportPersonalAccessClient();

        $email = (string) config('toweros.platform_super_admin_email');
        $password = (string) config('toweros.platform_dev_password');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Platform Super Administrator',
                'password' => $password,
                'is_platform_admin' => true,
                'platform_role' => 'superadmin',
            ],
        );

        app(OperationalAcronymService::class)->syncDefaults(OperationalAcronymDefaults::all());

        $this->call(DevDefaultTenantSeeder::class);
    }

    private function ensurePassportPersonalAccessClient(): void
    {
        $repository = app(ClientRepository::class);
        $provider = (string) config('auth.guards.api.provider', 'users');

        try {
            $repository->personalAccessClient($provider);
        } catch (RuntimeException) {
            $repository->createPersonalAccessGrantClient(
                config('app.name').' Personal Access Client',
                $provider,
            );
        }
    }
}
