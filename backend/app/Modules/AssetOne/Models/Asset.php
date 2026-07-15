<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasUuids;

    protected $table = 'assets';

    protected $fillable = [
        'asset_code',
        'name',
        'category',
        'status',
        'rfid_tag',
        'location_type',
        'location_id',
        'warranty_expiry',
        'purchase_value',
        'source_grn_line_id',
        'source_po_line_id',
    ];

    protected function casts(): array
    {
        return [
            'warranty_expiry' => 'date',
            'purchase_value' => 'decimal:2',
        ];
    }
}
