<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use App\Modules\Rollout\Support\WorkingDaysCalendar;
use App\Modules\Tenancy\Support\CorsAllowedOriginResolver;
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
        $this->app->singleton(CorsAllowedOriginResolver::class);

        $this->app->singleton(WorkingDaysCalendar::class, static function ($app) {
            return $app->make(TenantWorkingDaysCalendarFactory::class)->make();
        });
    }

    public function boot(): void
    {
        $this->configureSanctumStatefulDomains();
        $this->configureCorsAllowedOrigins();

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('e-approval-public', function (Request $request) {
            $perMinute = max(10, (int) config('e_approval.public_links.rate_limit_per_minute', 30));

            return Limit::perMinute($perMinute)->by($request->ip().'|'.($request->route('token') ?? 'global'));
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

    private function configureCorsAllowedOrigins(): void
    {
        try {
            $resolver = $this->app->make(CorsAllowedOriginResolver::class);

            config([
                'cors.allowed_origins' => $resolver->resolve(),
                'cors.allowed_origins_patterns' => $resolver->resolvePatterns(),
            ]);
        } catch (\Throwable) {
            // Keep config/cors.php defaults during early install or migrate.
        }
    }
}
