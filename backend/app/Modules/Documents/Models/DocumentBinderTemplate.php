<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentBinderTemplate extends Model
{
    use HasUuids;

    protected $table = 'document_binder_templates';

    protected $fillable = [
        'tree_json',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'tree_json' => 'array',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'updated_by_id');
    }
}
