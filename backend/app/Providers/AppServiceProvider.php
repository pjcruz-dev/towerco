<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use App\Modules\Rollout\Support\WorkingDaysCalendar;
use App\Modules\Tenancy\Support\SanctumStatefulDomainResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SanctumStatefulDomainResolver::class);

        $this->app->singleton(WorkingDaysCalendar::class, static function ($app) {
            return $app->make(TenantWorkingDaysCalendarFactory::class)->make();
        });
    }

    public function boot(): void
    {
        $this->configureSanctumStatefulDomains();

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        if (class_exists(\Laravel\Horizon\Horizon::class)) {
            \Laravel\Horizon\Horizon::auth(function ($request) {
                if (app()->environment('local')) {
                    return true;
                }

                /** @var User|null $user */
                $user = $request->user();

                return $user instanceof User && $user->isPlatformAdmin();
            });
        }
    }

    private function configureSanctumStatefulDomains(): void
    {
        try {
            config([
                'sanctum.stateful' => $this->app->make(SanctumStatefulDomainResolver::class)->resolve(),
            ]);
        } catch (\Throwable) {
            // Keep config/sanctum.php defaults during early install or migrate.
        }
    }
}
