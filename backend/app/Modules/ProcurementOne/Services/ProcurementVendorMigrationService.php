<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use App\Modules\EApproval\Services\EApprovalVendorRegistrationMasterDataService;
use App\Modules\ProcurementOne\Models\ProcurementVendor;

final class ProcurementVendorMigrationService
{
    public function __construct(
        private readonly ProcurementVendorSyncService $sync,
    ) {}

    /**
     * @return array{created: int, updated: int, total: int}
     */
    public function migrateFromMasterData(): array
    {
        $set = EApprovalMasterDataSet::query()
            ->where('key', EApprovalVendorRegistrationMasterDataService::VENDORS_SET_KEY)
            ->first();

        if (! $set instanceof EApprovalMasterDataSet) {
            return ['created' => 0, 'updated' => 0, 'total' => 0];
        }

        $created = 0;
        $updated = 0;

        $set->rows()->orderBy('label')->each(function (EApprovalMasterDataRow $row) use (&$created, &$updated): void {
            $existed = ProcurementVendor::query()
                ->where('master_data_row_id', (string) $row->id)
                ->exists();
            $this->sync->syncFromMasterDataRow($row);
            if ($existed) {
                $updated++;
            } else {
                $created++;
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
        ];
    }
}
