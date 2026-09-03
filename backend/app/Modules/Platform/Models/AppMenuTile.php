<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppMenuTile extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'group_id',
        'title',
        'subtitle',
        'icon',
        'icon_asset',
        'icon_url',
        'accent',
        'href',
        'open_in_new_tab',
        'sort_order',
        'is_visible',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'open_in_new_tab' => 'boolean',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'group_id' => $this->group_id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'icon' => $this->icon,
            'icon_url' => $this->icon_url,
            'accent' => $this->accent,
            'href' => $this->href,
            'open_in_new_tab' => (bool) $this->open_in_new_tab,
            'sort_order' => (int) $this->sort_order,
            'is_visible' => (bool) $this->is_visible,
            'is_system' => (bool) $this->is_system,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'group_id' => $this->group_id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'icon' => $this->icon,
            'icon_url' => $this->icon_url,
            'accent' => $this->accent,
            'href' => $this->href,
            'open_in_new_tab' => (bool) $this->open_in_new_tab,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
