<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleListSharedLayout extends BaseModel
{
    protected $table = 'module_list_shared_layouts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'storage_key',
        'layouts_json',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layouts_json' => 'array',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'updated_by');
    }
}
