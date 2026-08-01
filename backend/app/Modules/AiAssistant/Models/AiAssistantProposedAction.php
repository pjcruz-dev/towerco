<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAssistantProposedAction extends Model
{
    use HasUuids;

    protected $table = 'ai_assistant_proposed_actions';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'message_id',
        'action',
        'status',
        'payload',
        'preview',
        'result_entity_type',
        'result_entity_id',
        'result_meta',
        'rejection_reason',
        'expires_at',
        'confirmed_at',
        'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'preview' => 'array',
            'result_meta' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    /** @return BelongsTo<AiMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'message_id');
    }
}
