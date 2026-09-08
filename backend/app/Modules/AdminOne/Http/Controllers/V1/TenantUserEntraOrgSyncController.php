<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Jobs\SyncEntraOrgDirectoryJob;
use App\Modules\Identity\Services\EntraOrgDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

final class TenantUserEntraOrgSyncController extends AbstractApiController
{
    public function __invoke(Request $request, EntraOrgDirectoryService $org): JsonResponse
    {
        abort_unless(
            $request->user()?->can('organization:manage')
            || $request->user()?->can('user:manage'),
            403,
        );

        $tenantId = (string) (tenant('id') ?? '');
        if ($tenantId === '') {
            return $this->ok([
                'ok' => false,
                'code' => 'sync_failed',
                'message' => 'Tenant context is missing for organization sync.',
                'scanned' => 0,
                'updated' => 0,
                'managers_linked' => 0,
                'skipped_unlicensed' => 0,
            ]);
        }

        $preflight = $org->preflightSyncOrFailure();
        if ($preflight !== null) {
            return $this->ok($preflight);
        }

        try {
            // ACK immediately so nginx/gateway (~60s) cannot return 504 while Graph sync runs.
            // Continues after the HTTP response (fastcgi_finish_request) — no queue worker required.
            Bus::dispatchAfterResponse(function () use ($tenantId): void {
                (new SyncEntraOrgDirectoryJob($tenantId, true))
                    ->handle(app(EntraOrgDirectoryService::class));
            });
        } catch (\Throwable $exception) {
            Log::error('Entra org sync could not be dispatched', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return $this->ok([
                'ok' => false,
                'code' => 'sync_failed',
                'message' => 'Organization sync failed to start: '.$exception->getMessage(),
                'scanned' => 0,
                'updated' => 0,
                'managers_linked' => 0,
                'skipped_unlicensed' => 0,
            ]);
        }

        return $this->ok([
            'ok' => true,
            'code' => 'started',
            'message' => 'Organization sync started in the background. Refresh this page in about a minute — manager, title, department, and license changes from Microsoft will appear when it finishes.',
            'scanned' => 0,
            'updated' => 0,
            'managers_linked' => 0,
            'skipped_unlicensed' => 0,
        ]);
    }
}
