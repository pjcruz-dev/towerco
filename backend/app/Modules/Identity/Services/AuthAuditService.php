<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use App\Modules\Workspace\Support\WorkspaceAuditActionLabel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthAuditService
{
    public function __construct(
        private readonly TenantActivityLogger $activity,
    ) {}

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

        if (tenant() === null) {
            return;
        }

        $actor = Auth::user();
        if (! $actor instanceof TenantUser && isset($context['revoked_by']) && is_string($context['revoked_by'])) {
            $actor = TenantUser::query()->find($context['revoked_by']);
        }
        if (! $actor instanceof TenantUser && $userId !== null) {
            $actor = TenantUser::query()->find($userId);
        }

        $metadata = $context;
        $metadata['risk_level'] = $riskLevel;
        if ($sessionId !== null && $sessionId !== '') {
            $metadata['session_id'] = $sessionId;
        }

        $entityLabel = null;
        if ($userId !== null) {
            $target = TenantUser::query()->find($userId);
            $entityLabel = $target?->email;
        }

        $this->activity->record(
            module: 'team_access',
            action: $event,
            summary: WorkspaceAuditActionLabel::label($event),
            entityType: 'user',
            entityId: $userId,
            entityLabel: $entityLabel,
            actor: $actor instanceof TenantUser ? $actor : null,
            metadata: $metadata,
        );
    }
}
