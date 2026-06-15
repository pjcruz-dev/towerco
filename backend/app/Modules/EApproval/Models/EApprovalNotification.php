<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\EApproval\Support\EApprovalNotificationCategory;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalNotification extends Model
{
    use HasUuids;

    protected $table = 'e_approval_notifications';

    protected $fillable = [
        'user_id',
        'actor_user_id',
        'actor_name',
        'type',
        'category',
        'submission_id',
        'document_no',
        'form_name',
        'message',
        'body_preview',
        'href',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'actor_user_id');
    }

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'submission_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $type = (string) $this->type;
        $submissionId = $this->submission_id ? (string) $this->submission_id : null;
        $category = (string) ($this->category ?: EApprovalNotificationCategory::forType($type));
        $href = (string) ($this->href ?: EApprovalNotificationCategory::hrefFor($type, $submissionId));

        return [
            'id' => (string) $this->id,
            'type' => $type,
            'category' => $category,
            'submission_id' => $submissionId,
            'document_no' => $this->document_no,
            'form_name' => $this->form_name,
            'actor_user_id' => $this->actor_user_id ? (string) $this->actor_user_id : null,
            'actor_name' => $this->actor_name,
            'message' => $this->message,
            'body_preview' => $this->body_preview,
            'href' => $href,
            'is_read' => (bool) $this->is_read,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
