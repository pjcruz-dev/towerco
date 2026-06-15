<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Support\TenantNotificationCategoryResolver;
use App\Modules\Notifications\Support\TenantNotificationModule;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantNotification extends Model
{
    use HasUuids;

    protected $table = 'tenant_notifications';

    protected $fillable = [
        'user_id',
        'actor_user_id',
        'actor_name',
        'module',
        'type',
        'category',
        'subject_type',
        'subject_id',
        'context_primary',
        'context_secondary',
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

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $module = (string) $this->module;
        $type = (string) $this->type;
        $subjectId = $this->subject_id ? (string) $this->subject_id : null;
        $category = (string) ($this->category ?: TenantNotificationCategoryResolver::for($module, $type));
        $href = (string) ($this->href ?: TenantNotificationCategoryResolver::hrefFor(
            $module,
            $type,
            $this->subject_type,
            $subjectId,
        ));

        $payload = [
            'id' => (string) $this->id,
            'module' => $module,
            'type' => $type,
            'category' => $category,
            'subject_type' => $this->subject_type,
            'subject_id' => $subjectId,
            'context_primary' => $this->context_primary,
            'context_secondary' => $this->context_secondary,
            'actor_user_id' => $this->actor_user_id ? (string) $this->actor_user_id : null,
            'actor_name' => $this->actor_name,
            'message' => $this->message,
            'body_preview' => $this->body_preview,
            'href' => $href,
            'is_read' => (bool) $this->is_read,
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($module === TenantNotificationModule::E_APPROVAL) {
            $payload['submission_id'] = $this->subject_type === 'submission' ? $subjectId : null;
            $payload['document_no'] = $this->context_primary;
            $payload['form_name'] = $this->context_secondary;
        }

        return $payload;
    }
}
