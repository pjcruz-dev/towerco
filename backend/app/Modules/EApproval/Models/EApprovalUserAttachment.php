<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalUserAttachment extends Model
{
    use HasUuids;

    protected $table = 'e_approval_user_attachments';

    protected $fillable = ['user_id', 'file_path', 'file_name', 'metadata'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }
}
