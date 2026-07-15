<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\ProcurementOne\Models\ProcurementContract;

final class ProcurementContractDocumentSyncService
{
    public function syncPrimaryDocumentExpiry(ProcurementContract $contract): void
    {
        if ($contract->primary_document_id === null || $contract->end_date === null) {
            return;
        }

        Document::query()
            ->whereKey($contract->primary_document_id)
            ->update([
                'expires_at' => $contract->end_date->endOfDay(),
                'last_touched_at' => now(),
            ]);
    }

    public function clearPrimaryDocumentExpiry(ProcurementContract $contract): void
    {
        if ($contract->primary_document_id === null) {
            return;
        }

        Document::query()
            ->whereKey($contract->primary_document_id)
            ->update([
                'expires_at' => null,
                'last_touched_at' => now(),
            ]);
    }
}
