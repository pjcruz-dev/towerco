<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetRepair extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_repairs';

    protected $fillable = [
        'asset_id', 'status', 'usable', 'replacement_required', 'problem_type', 'severity',
        'problem', 'notes', 'diagnosis', 'result', 'vendor_email_subject', 'vendor_email_body',
        'reported_by', 'reported_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'usable' => 'boolean',
            'replacement_required' => 'boolean',
            'reported_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return HasMany<AssetRepairReplacement, $this> */
    public function replacements(): HasMany
    {
        return $this->hasMany(AssetRepairReplacement::class, 'repair_id');
    }
}
