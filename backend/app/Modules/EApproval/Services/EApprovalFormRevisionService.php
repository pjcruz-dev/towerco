<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;

final class EApprovalFormRevisionService
{
    private const MAX_REVISIONS = 30;

    /**
     * @return list<array<string, mixed>>
     */
    public function list(EApprovalForm $form): array
    {
        $meta = is_array($form->metadata_json) ? $form->metadata_json : [];
        $revisions = $meta['revisions'] ?? [];

        return is_array($revisions) ? array_values($revisions) : [];
    }

    public function record(EApprovalForm $form, TenantUser $actor, string $event): void
    {
        $form->loadMissing(['fields', 'workflowTemplate.steps']);
        $meta = is_array($form->metadata_json) ? $form->metadata_json : [];
        $revisions = is_array($meta['revisions'] ?? null) ? $meta['revisions'] : [];

        $revisionNo = count($revisions) + 1;
        $label = $form->status === 'published'
            ? "Published v{$revisionNo}"
            : "Draft v{$revisionNo}";

        $revisions[] = [
            'revision' => $revisionNo,
            'label' => $label,
            'event' => $event,
            'status' => (string) $form->status,
            'schema_version' => (int) $form->schema_version,
            'field_count' => $form->fields->count(),
            'step_count' => $form->workflowTemplate?->steps->count() ?? 0,
            'saved_at' => now()->toIso8601String(),
            'saved_by' => [
                'id' => (string) $actor->id,
                'name' => (string) $actor->name,
            ],
            'snapshot' => $form->toDetailPayload(),
        ];

        if (count($revisions) > self::MAX_REVISIONS) {
            $revisions = array_slice($revisions, -self::MAX_REVISIONS);
        }

        $meta['revisions'] = array_values($revisions);
        $form->metadata_json = $meta;
        $form->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshotFor(EApprovalForm $form, int $revision): ?array
    {
        foreach ($this->list($form) as $entry) {
            if ((int) ($entry['revision'] ?? 0) === $revision) {
                $snapshot = $entry['snapshot'] ?? null;

                return is_array($snapshot) ? $snapshot : null;
            }
        }

        return null;
    }
}
