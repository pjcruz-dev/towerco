<?php

declare(strict_types=1);

namespace App\Modules\Sites\Models;

use App\Modules\ProjectOne\Models\Project;
use App\Modules\TowerOne\Models\Tower;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'site_id');
    }

    /** @return HasMany<Tower, $this> */
    public function towers(): HasMany
    {
        return $this->hasMany(Tower::class, 'site_id');
    }
}
