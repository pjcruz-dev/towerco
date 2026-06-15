<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EApprovalMasterDataSet extends Model
{
    use HasUuids;

    protected $table = 'e_approval_master_data_sets';

    protected $fillable = ['key', 'name', 'status', 'config_json'];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
        ];
    }

    /** @return HasMany<EApprovalMasterDataRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(EApprovalMasterDataRow::class, 'set_id');
    }
}
