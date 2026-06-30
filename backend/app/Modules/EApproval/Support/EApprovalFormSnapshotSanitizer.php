<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalForm;

final class EApprovalFormSnapshotSanitizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function stripNestedHistory(array $payload): array
    {
        unset($payload['published_snapshot'], $payload['revisions']);

        if (isset($payload['metadata_json']) && is_array($payload['metadata_json'])) {
            $payload['metadata_json'] = self::stripRevisionsFromMetadata($payload['metadata_json']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function stripRevisionsFromMetadata(array $metadata): array
    {
        unset($metadata['revisions']);

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public static function sanitizeRevisionEntry(array $entry): array
    {
        if (isset($entry['snapshot']) && is_array($entry['snapshot'])) {
            $entry['snapshot'] = self::stripNestedHistory($entry['snapshot']);
        }

        return $entry;
    }

    public static function compactStoredMetadata(EApprovalForm $form): bool
    {
        $meta = is_array($form->metadata_json) ? $form->metadata_json : [];
        $revisions = is_array($meta['revisions'] ?? null) ? $meta['revisions'] : [];
        $changed = false;

        $sanitizedRevisions = [];
        foreach ($revisions as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $clean = self::sanitizeRevisionEntry($entry);
            $sanitizedRevisions[] = $clean;

            if ($clean !== $entry) {
                $changed = true;
            }
        }

        $meta['revisions'] = array_values($sanitizedRevisions);

        if ($meta !== $form->metadata_json) {
            $form->metadata_json = $meta;
            $changed = true;
        }

        $form->loadMissing(['fields', 'workflowTemplate.steps']);
        $storageJson = json_encode($form->toStorageSnapshot(), JSON_THROW_ON_ERROR);

        if ($form->published_snapshot !== $storageJson) {
            $form->published_snapshot = $storageJson;
            $changed = true;
        }

        if ($changed) {
            $form->save();
        }

        return $changed;
    }
}
