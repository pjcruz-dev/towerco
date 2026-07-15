<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementLifecycleEvent;
use App\Modules\ProcurementOne\Models\ProcurementPoPrLink;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use Illuminate\Support\Str;

final class ProcurementLifecycleAuditService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $documentType,
        string $documentId,
        ?string $documentNo,
        string $action,
        ?TenantUser $actor,
        ?string $reason = null,
        array $metadata = [],
    ): ProcurementLifecycleEvent {
        return ProcurementLifecycleEvent::query()->create([
            'id' => (string) Str::uuid(),
            'document_type' => $documentType,
            'document_id' => $documentId,
            'document_no' => $documentNo,
            'action' => $action,
            'reason' => $reason,
            'actor_user_id' => $actor !== null ? (string) $actor->id : null,
            'metadata_json' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForDocument(string $documentType, string $documentId, int $limit = 20): array
    {
        return ProcurementLifecycleEvent::query()
            ->with('actor:id,name')
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(static fn (ProcurementLifecycleEvent $event) => [
                'id' => (string) $event->id,
                'action' => $event->action,
                'reason' => $event->reason,
                'document_no' => $event->document_no,
                'actor' => $event->actor ? [
                    'id' => (string) $event->actor->id,
                    'name' => $event->actor->name,
                ] : null,
                'metadata' => $event->metadata_json ?? [],
                'created_at' => $event->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function hasActivePurchaseOrderCommitments(string $prId): bool
    {
        return ProcurementPoPrLink::query()
            ->where('pr_id', $prId)
            ->whereHas('po', static fn ($q) => $q->whereNotIn('status', [
                ProcurementPoStatus::CANCELLED,
                ProcurementPoStatus::VOIDED,
            ]))
            ->exists();
    }
}
