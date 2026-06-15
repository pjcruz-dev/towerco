<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Platform\Support\StructuredAuditLogWriter;

final class PlatformAuthAuditService
{
    public function __construct(
        private readonly StructuredAuditLogWriter $auditWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, ?User $actor = null, array $context = [], string $riskLevel = 'low'): void
    {
        $this->auditWriter->write('platform.auth', $event, array_merge([
            'actor_user_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'risk_level' => $riskLevel,
            'context' => $context,
        ]));
    }
}
