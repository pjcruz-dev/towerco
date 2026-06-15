<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EApprovalPublicFormLink extends Model
{
    use HasUuids;

    protected $table = 'e_approval_public_form_links';

    protected $fillable = [
        'form_id',
        'label',
        'token_hash',
        'password_hash',
        'sponsor_user_id',
        'created_by_user_id',
        'is_enabled',
        'expires_at',
        'max_submissions',
        'submissions_count',
        'revoked_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'expires_at' => 'datetime',
            'max_submissions' => 'integer',
            'submissions_count' => 'integer',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EApprovalForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(EApprovalForm::class, 'form_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'sponsor_user_id');
    }

    /** @return HasMany<EApprovalSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(EApprovalSubmission::class, 'public_link_id');
    }

    public function isActive(): bool
    {
        if (! $this->is_enabled || $this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_submissions !== null && $this->submissions_count >= $this->max_submissions) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminRow(): array
    {
        $this->loadMissing(['sponsor:id,name,email']);

        return [
            'id' => (string) $this->id,
            'form_id' => (string) $this->form_id,
            'label' => $this->label,
            'is_enabled' => $this->is_enabled,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'max_submissions' => $this->max_submissions,
            'submissions_count' => $this->submissions_count,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'sponsor' => $this->sponsor ? [
                'id' => (string) $this->sponsor->id,
                'name' => $this->sponsor->name,
                'email' => $this->sponsor->email,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
