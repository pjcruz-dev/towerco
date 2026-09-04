<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Services\EntraOrgDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        set_time_limit(180);
        ignore_user_abort(true);

        try {
            return $this->ok($org->syncDirectoryFromApp());
        } catch (\Throwable $exception) {
            Log::error('Entra org sync failed', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return $this->ok([
                'ok' => false,
                'code' => 'sync_failed',
                'message' => 'Organization sync failed: '.$exception->getMessage(),
                'scanned' => 0,
                'updated' => 0,
                'managers_linked' => 0,
                'skipped_unlicensed' => 0,
            ]);
        }
    }
}
