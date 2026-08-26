<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Modules\Notifications\Mail\MicrosoftGraphMailTokenProvider;
use App\Modules\Notifications\Mail\MicrosoftGraphTransport;
use App\Modules\Rollout\Contracts\ReverseGeocoderInterface;
use App\Modules\Rollout\Services\ReverseGeocodeService;
use App\Modules\Rollout\Support\TenantWorkingDaysCalendarFactory;
use App\Modules\Rollout\Support\WorkingDaysCalendar;
use App\Modules\Tenancy\Support\CorsAllowedOriginResolver;
use App\Modules\Tenancy\Support\SanctumStatefulDomainResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SanctumStatefulDomainResolver::class);
        $this->app->singleton(CorsAllowedOriginResolver::class);

        $this->app->singleton(WorkingDaysCalendar::class, static function ($app) {
            return $app->make(TenantWorkingDaysCalendarFactory::class)->make();
        });

        $this->app->bind(ReverseGeocoderInterface::class, static function () {
            return ReverseGeocodeService::resolveDriver();
        });
    }

    public function boot(): void
    {
        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            URL::forceRootUrl($appUrl);
        }

        $this->registerMicrosoftGraphMailTransport();
        $this->configureSanctumStatefulDomains();
        $this->configureCorsAllowedOrigins();

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('e-approval-public', function (Request $request) {
            $perMinute = max(10, (int) config('e_approval.public_links.rate_limit_per_minute', 30));

            return Limit::perMinute($perMinute)->by($request->ip().'|'.($request->route('token') ?? 'global'));
        });

        RateLimiter::for('procurement-public', function (Request $request) {
            $perMinute = max(10, (int) config('procurement_one.public_quotes.rate_limit_per_minute', 30));

            return Limit::perMinute($perMinute)->by($request->ip().'|'.($request->route('token') ?? 'global'));
        });

        RateLimiter::for('assistant', function (Request $request) {
            $perMinute = max(5, (int) config('ai_assistant.rate_limit_per_minute', 20));

            return Limit::perMinute($perMinute)->by(
                ($request->user()?->getAuthIdentifier() ?: $request->ip()).'|assistant',
            );
        });

        RateLimiter::for('geocode', function (Request $request) {
            return Limit::perMinute(30)->by(
                ($request->user()?->getAuthIdentifier() ?: $request->ip()).'|geocode',
            );
        });

        if (class_exists(Horizon::class)) {
            Horizon::auth(function ($request) {
                if (app()->environment('local')) {
                    return true;
                }

                /** @var User|null $user */
                $user = $request->user();

                return $user instanceof User && $user->isPlatformAdmin();
            });
        }
    }

    private function registerMicrosoftGraphMailTransport(): void
    {
        Mail::extend('microsoft-graph', function (array $config) {
            return new MicrosoftGraphTransport(
                $this->app->make(MicrosoftGraphMailTokenProvider::class),
                (bool) ($config['save_to_sent_items'] ?? config('services.microsoft_graph_mail.save_to_sent_items', false)),
            );
        });
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
