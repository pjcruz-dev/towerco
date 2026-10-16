<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetDisposal extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_disposals';

    protected $fillable = [
        'asset_id', 'disposition', 'disposition_date', 'approved_by', 'amount', 'buyer_vendor', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'disposition_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }
}
