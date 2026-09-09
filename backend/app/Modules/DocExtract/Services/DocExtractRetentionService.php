<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use App\Modules\DocExtract\Models\DocExtractDocument;
use Carbon\CarbonImmutable;

final class DocExtractRetentionService
{
    public function __construct(
        private readonly DocExtractFileStorageService $storage,
    ) {}

    public function pruneExpired(?int $days = null): int
    {
        $days = max(1, $days ?? (int) config('doc_extract.retention_days', 7));
        $cutoff = CarbonImmutable::now()->subDays($days);
        $pruned = 0;

        DocExtractDocument::query()
            ->whereNull('purged_at')
            ->whereNotNull('stored_path')
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->chunkById(50, function ($documents) use (&$pruned): void {
                foreach ($documents as $document) {
                    /** @var DocExtractDocument $document */
                    if (is_string($document->stored_path) && $document->stored_path !== '') {
                        $this->storage->delete($document->stored_path);
                    }
                    $document->stored_path = null;
                    $document->purged_at = now();
                    $document->save();
                    $pruned++;
                }
            });

        return $pruned;
    }
}
