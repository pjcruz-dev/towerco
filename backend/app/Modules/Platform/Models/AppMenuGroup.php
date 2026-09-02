<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppMenuGroup extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'title',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AppMenuTile, $this>
     */
    public function tiles(): HasMany
    {
        return $this->hasMany(AppMenuTile::class, 'group_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'sort_order' => (int) $this->sort_order,
            'is_visible' => (bool) $this->is_visible,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tiles
     * @return array<string, mixed>
     */
    public function toPublicArray(array $tiles = []): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'sort_order' => (int) $this->sort_order,
            'tiles' => $tiles,
        ];
    }
}
