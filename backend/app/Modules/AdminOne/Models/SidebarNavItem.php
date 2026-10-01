<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SidebarNavItem extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $connection = 'tenant';

    protected $table = 'sidebar_nav_items';

    protected $fillable = [
        'parent_id',
        'key',
        'type',
        'title',
        'icon',
        'href',
        'entity_slug',
        'permission_key',
        'required_permissions',
        'permissions_match',
        'module',
        'sort_order',
        'is_system',
        'is_visible',
        'is_global_default',
    ];

    protected function casts(): array
    {
        return [
            'required_permissions' => 'array',
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_visible' => 'boolean',
            'is_global_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<SidebarNavItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<SidebarNavItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
