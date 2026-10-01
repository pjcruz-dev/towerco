<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use HasUuids;

    protected $table = 'sites';

    protected $fillable = [
        'site_code',
        'name',
        'full_address',
        'latitude',
        'longitude',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }
}
