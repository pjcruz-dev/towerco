<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Support\DocumentStatus;
use Carbon\Carbon;

final class DocumentExpiringService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(int $withinDays = 90): array
    {
        $until = Carbon::now()->addDays(max(1, $withinDays));

        return Document::query()
            ->whereNull('deleted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $until)
            ->whereIn('status', [DocumentStatus::DRAFT, DocumentStatus::FINAL])
            ->with(['site:id,site_code,name', 'lastTouchedBy:id,name'])
            ->orderBy('expires_at')
            ->limit(200)
            ->get()
            ->map(static fn (Document $doc) => [
                'id' => (string) $doc->id,
                'title' => $doc->title,
                'expires_at' => $doc->expires_at?->toIso8601String(),
                'status' => $doc->status,
                'site' => $doc->site ? [
                    'id' => (string) $doc->site->id,
                    'site_code' => $doc->site->site_code,
                    'name' => $doc->site->name,
                ] : null,
                'last_touched_by' => $doc->lastTouchedBy ? [
                    'id' => (string) $doc->lastTouchedBy->id,
                    'name' => $doc->lastTouchedBy->name,
                ] : null,
                'last_touched_at' => $doc->last_touched_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{within_30: int, within_60: int, within_90: int}
     */
    public function summaryCounts(): array
    {
        $now = Carbon::now();

        return [
            'within_30' => $this->countUntil($now->copy()->addDays(30)),
            'within_60' => $this->countUntil($now->copy()->addDays(60)),
            'within_90' => $this->countUntil($now->copy()->addDays(90)),
        ];
    }

    private function countUntil(Carbon $until): int
    {
        return (int) Document::query()
            ->whereNull('deleted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $until)
            ->whereIn('status', [DocumentStatus::DRAFT, DocumentStatus::FINAL])
            ->count();
    }
}
