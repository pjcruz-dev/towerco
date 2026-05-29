<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Services;

use App\Modules\Rollout\Models\TenantRolloutFile;
use App\Modules\Rollout\Services\TenantFileStorageService;

final class ProjectApprovalAttachmentService
{
    public function __construct(
        private readonly TenantFileStorageService $files,
    ) {}

    /**
     * @param  list<string>|null  $fileIds
     * @return list<string>|null
     */
    public function normalize(?array $fileIds, ?string $rolloutProgramId): ?array
    {
        if ($fileIds === null) {
            return null;
        }

        if ($fileIds === []) {
            return [];
        }

        if ($rolloutProgramId === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'attachment_file_ids' => [__('Rollout is required when attaching files.')],
            ]);
        }

        $this->files->assertFilesBelongToRollout($fileIds, $rolloutProgramId);

        return array_values(array_unique($fileIds));
    }

    /**
     * @param  list<string>|null  $fileIds
     * @return list<array{file_id: string, url: string, label: string, mime_type: string}>|null
     */
    public function enrich(?array $fileIds): ?array
    {
        if ($fileIds === null || $fileIds === []) {
            return $fileIds;
        }

        $enriched = [];
        foreach ($fileIds as $fileId) {
            $file = TenantRolloutFile::query()->find($fileId);
            if ($file === null) {
                continue;
            }

            $enriched[] = [
                'file_id' => $file->id,
                'url' => $this->files->apiUrl($file),
                'label' => $file->original_filename,
                'mime_type' => $file->mime_type,
            ];
        }

        return $enriched;
    }
}
