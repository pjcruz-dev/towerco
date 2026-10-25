<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

use App\Modules\EApproval\Models\EApprovalMasterDataSet;

/**
 * Register rows store the master-data name. The form still keeps the short code.
 */
final class ControlledDocumentMasterLabel
{
    public function documentType(?string $value): ?string
    {
        return $this->expand('document_types', $value);
    }

    public function department(?string $value): ?string
    {
        return $this->expand('departments', $value);
    }

    public function expand(string $setKey, ?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $set = EApprovalMasterDataSet::query()
            ->where('key', $setKey)
            ->where('status', 'active')
            ->first();
        if ($set === null) {
            return $value;
        }

        $needle = mb_strtolower($value);
        $row = $set->rows()
            ->where('is_active', true)
            ->get(['code', 'label'])
            ->first(static function ($candidate) use ($needle): bool {
                return mb_strtolower(trim((string) $candidate->code)) === $needle;
            });

        $label = trim((string) ($row?->label ?? ''));

        return $label !== '' ? $label : $value;
    }
}
