<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class RolloutBulkUpdateService
{
    public function __construct(
        private readonly RolloutProgramService $programService,
        private readonly RolloutAuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $rolloutIds
     * @param  array<string, mixed>  $updates
     * @return array{
     *     updated: int,
     *     failed: int,
     *     results: list<array{id: string, status: string, reason?: string}>
     * }
     */
    public function bulkUpdate(array $rolloutIds, array $updates, ?Authenticatable $actor = null): array
    {
        if ($updates === []) {
            throw ValidationException::withMessages([
                'updates' => [__('At least one field must be provided for bulk update.')],
            ]);
        }

        $results = [];
        $updated = 0;
        $failed = 0;
        $updatedIds = [];

        foreach ($rolloutIds as $rolloutId) {
            /** @var RolloutProgram|null $program */
            $program = RolloutProgram::query()->find($rolloutId);

            if ($program === null) {
                $results[] = [
                    'id' => (string) $rolloutId,
                    'status' => 'failed',
                    'reason' => __('Rollout not found.'),
                ];
                $failed++;

                continue;
            }

            try {
                $this->programService->updateMetadata($program, $updates);
                $results[] = [
                    'id' => (string) $program->id,
                    'status' => 'updated',
                ];
                $updated++;
                $updatedIds[] = (string) $program->id;
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();
                $results[] = [
                    'id' => (string) $program->id,
                    'status' => 'skipped',
                    'reason' => is_string($message) ? $message : __('Rollout could not be updated.'),
                ];
                $failed++;
            }
        }

        if ($updatedIds !== []) {
            $this->audit->logBulkMetadataUpdated($updatedIds, $updates, $actor ?? Auth::user());
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}
