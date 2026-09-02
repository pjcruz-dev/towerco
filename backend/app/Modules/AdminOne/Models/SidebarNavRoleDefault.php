<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidebarNavRoleDefault extends Model
{
    protected $connection = 'tenant';

    protected $table = 'sidebar_nav_role_defaults';

    protected $primaryKey = 'role_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'role_id',
        'item_id',
    ];

    /** @return BelongsTo<SidebarNavItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SidebarNavItem::class, 'item_id');
    }
}
