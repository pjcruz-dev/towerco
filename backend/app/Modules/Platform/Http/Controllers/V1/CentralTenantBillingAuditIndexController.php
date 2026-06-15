<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\TenantBillingAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralTenantBillingAuditIndexController extends AbstractApiController
{
    public function __invoke(Request $request, Tenant $tenant): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('limit', 50)));

        $rows = TenantBillingAuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(static fn (TenantBillingAuditLog $log): array => [
                'id' => $log->id,
                'actor_email' => $log->actor_email,
                'changes' => $log->changes,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return $this->ok($rows);
    }
}
