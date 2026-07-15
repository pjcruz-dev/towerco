<?php

declare(strict_types=1);

namespace App\Console\Commands\Dev;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpFoundation\Response;

/**
 * Local-only: measure in-process route dispatch time (excludes HTTP/TCP).
 * Run inside tenant context: php artisan tenants:run profile:tenant-routes --tenants=<id>
 */
class ProfileTenantRoutesCommand extends Command
{
    protected $signature = 'profile:tenant-routes
        {--limit=40 : Max routes to profile}
        {--prefix= : Only routes starting with this URI prefix (e.g. api/v1/e-approval)}';

    protected $description = 'Local dev: profile tenant route dispatch times (in-process, no HTTP)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This command is local-only. Set APP_ENV=local.');

            return self::FAILURE;
        }

        $prefix = (string) $this->option('prefix');
        $limit = max(1, (int) $this->option('limit'));

        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('GET', $route->methods(), true))
            ->filter(fn (Route $route) => ! str_contains($route->uri(), '{'))
            ->filter(function (Route $route) use ($prefix) {
                if ($prefix === '') {
                    return str_starts_with($route->uri(), 'api/v1/');
                }

                return str_starts_with($route->uri(), $prefix);
            })
            ->take($limit)
            ->values();

        if ($routes->isEmpty()) {
            $this->warn('No matching GET routes.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($routes as $route) {
            $uri = '/'.$route->uri();
            $warm = $this->dispatchOnce($route);
            $hot = $this->dispatchOnce($route);

            $rows[] = [
                'uri' => $uri,
                'name' => $route->getName() ?? '—',
                'cold_ms' => $warm,
                'warm_ms' => $hot,
                'status' => $hot['status'],
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['warm_ms']['ms'] <=> $a['warm_ms']['ms']);

        $this->table(
            ['URI', 'Warm ms', 'Cold ms', 'Status'],
            array_map(fn (array $r) => [
                $r['uri'],
                $r['warm_ms']['ms'],
                $r['cold_ms']['ms'],
                $r['status'],
            ], $rows),
        );

        $out = storage_path('app/local-route-profile.json');
        file_put_contents($out, json_encode([
            'generated_at' => now()->toIso8601String(),
            'tenant_id' => tenant()?->id,
            'rows' => $rows,
        ], JSON_PRETTY_PRINT));

        $this->info("Wrote {$out}");

        return self::SUCCESS;
    }

    /**
     * @return array{ms: float, status: int}
     */
    private function dispatchOnce(Route $route): array
    {
        $request = Request::create('/'.$route->uri(), 'GET');
        $request->headers->set('Accept', 'application/json');

        $start = microtime(true);

        try {
            /** @var Response $response */
            $response = app()->handle($request);
            $status = $response->getStatusCode();
        } catch (\Throwable) {
            $status = 500;
        }

        return [
            'ms' => round((microtime(true) - $start) * 1000, 2),
            'status' => $status,
        ];
    }
}
