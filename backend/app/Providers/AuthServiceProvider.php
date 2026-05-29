<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Windows Docker bind mounts often report 777 for oauth keys; chmod inside the container cannot fix that.
        if (filter_var(env('TOWEROS_DOCKER', false), FILTER_VALIDATE_BOOL)
            || filter_var(env('PASSPORT_DISABLE_KEY_PERMISSION_CHECK', false), FILTER_VALIDATE_BOOL)) {
            Passport::$validateKeyPermissions = false;
        }

        if (is_file(storage_path('oauth-private.key')) && is_file(storage_path('oauth-public.key'))) {
            Passport::loadKeysFrom(storage_path());
        }

        Passport::enablePasswordGrant();

        Passport::tokensExpireIn(now()->addHours((int) env('PASSPORT_ACCESS_TOKEN_TTL_HOURS', 12)));
        Passport::refreshTokensExpireIn(now()->addDays((int) env('PASSPORT_REFRESH_TOKEN_TTL_DAYS', 30)));
    }
}
