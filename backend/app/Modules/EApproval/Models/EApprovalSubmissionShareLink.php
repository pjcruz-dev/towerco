<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalSubmissionShareLink extends Model
{
    use HasUuids;

    protected $table = 'e_approval_submission_share_links';

    protected $fillable = [
        'id',
        'submission_id',
        'created_by_user_id',
        'token_hash',
        'label',
        'expires_at',
        'revoked_at',
        'last_accessed_at',
        'access_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'access_count' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'submission_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by_user_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminRow(?string $url = null): array
    {
        return [
            'id' => (string) $this->id,
            'submission_id' => (string) $this->submission_id,
            'label' => $this->label,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),
            'access_count' => (int) $this->access_count,
            'is_active' => $this->isActive(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_by' => $this->createdBy
                ? [
                    'id' => (string) $this->createdBy->id,
                    'name' => $this->createdBy->name,
                    'email' => $this->createdBy->email,
                ]
                : null,
            'url' => $url,
        ];
    }
}
