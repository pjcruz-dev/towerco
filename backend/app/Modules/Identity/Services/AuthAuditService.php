<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthAuditService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, ?string $userId, ?string $sessionId, array $context = [], string $riskLevel = 'low'): void
    {
        $jsonContext = null;
        if ($context !== []) {
            $jsonContext = json_encode($context);
        }

        DB::table('auth_audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'session_id' => $sessionId,
            'event' => $event,
            'risk_level' => $riskLevel,
            'ip_address' => request()->ip(),
            'context' => $jsonContext,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

