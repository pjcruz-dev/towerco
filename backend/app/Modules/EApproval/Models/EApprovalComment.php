<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EApprovalComment extends Model
{
    use HasUuids;

    protected $table = 'e_approval_comments';

    protected $fillable = [
        'submission_id',
        'user_id',
        'message',
        'parent_id',
        'attachment_id',
        'pin_page',
        'pin_x',
        'pin_y',
        'pin_w',
        'pin_h',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pin_page' => 'integer',
            'pin_x' => 'float',
            'pin_y' => 'float',
            'pin_w' => 'float',
            'pin_h' => 'float',
        ];
    }

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'submission_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    /** @return BelongsTo<EApprovalComment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<EApprovalComment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }
}
