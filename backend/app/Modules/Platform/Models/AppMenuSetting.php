<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppMenuSetting extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'grid_columns',
    ];

    protected function casts(): array
    {
        return [
            'grid_columns' => 'integer',
        ];
    }

    /**
     * @return array{grid_columns: int}
     */
    public function toApiArray(): array
    {
        return [
            'grid_columns' => max(3, min(6, (int) $this->grid_columns)),
        ];
    }
}
