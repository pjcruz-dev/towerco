<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TenantPublicHoliday extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'holiday_date',
        'name',
        'region',
        'calendar_year',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'calendar_year' => 'integer',
        ];
    }
}
