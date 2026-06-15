<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalMasterDataRow extends Model
{
    use HasUuids;

    protected $table = 'e_approval_master_data_rows';

    protected $fillable = ['set_id', 'code', 'label', 'data_json', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'data_json' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<EApprovalMasterDataSet, $this> */
    public function set(): BelongsTo
    {
        return $this->belongsTo(EApprovalMasterDataSet::class, 'set_id');
    }
}
