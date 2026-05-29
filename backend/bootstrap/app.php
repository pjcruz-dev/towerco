<?php

use App\Core\Exceptions\DomainException;
use App\Core\Http\Middleware\AssignCorrelationId;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api/v1', 'middleware' => ['tenant.sanctum', 'auth:sanctum', 'auth.session', 'auth.mfa']],
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            AssignCorrelationId::class,
        ]);

        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'tenant.sanctum' => \App\Core\Http\Middleware\ConfigureTenantSanctumProvider::class,
            'auth.session' => \App\Core\Http\Middleware\EnsureActiveSession::class,
            'auth.mfa' => \App\Core\Http\Middleware\EnsureMfaVerified::class,
            'platform.admin' => \App\Core\Http\Middleware\EnsurePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (DomainException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getErrorCode(),
            ], $e->getStatusCode());
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => __('Resource not found.'),
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $message = $e->getMessage();
            if ($message === '' || $message === 'HTTP error') {
                $message = Response::$statusTexts[$e->getStatusCode()] ?? 'HTTP error';
            }

            return response()->json([
                'message' => $message,
            ], $e->getStatusCode());
        });
    })->withSchedule(function (Schedule $schedule): void {
        if (class_exists(\Laravel\Horizon\Horizon::class)) {
            $schedule->command('horizon:snapshot')->everyFiveMinutes();
        }

        $schedule->command('rollout:gate-approvals:escalate')
            ->weekdays()
            ->dailyAt('08:00')
            ->withoutOverlapping();
    })->create();
