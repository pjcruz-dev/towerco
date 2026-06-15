<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

final class EApprovalAuditLogger
{
    public function log(string $action, ?string $targetId = null, ?string $remarks = null, ?Authenticatable $actor = null): void
    {
        EApprovalAuditLog::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $actor?->getAuthIdentifier(),
            'action' => $action,
            'target_id' => $targetId,
            'remarks' => $remarks,
        ]);
    }
}
