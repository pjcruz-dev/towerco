<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offboarding extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'offboarding';

    protected $fillable = [
        'number', 'employee_id', 'status', 'last_working_day', 'started_by', 'completed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'last_working_day' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /** @return HasMany<OffboardingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OffboardingItem::class, 'offboarding_id');
    }
}
