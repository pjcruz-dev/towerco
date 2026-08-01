<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    use HasUuids;

    protected $table = 'ai_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'module_context',
        'page_path',
        'status',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    /** @return HasMany<AiMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('created_at');
    }

    /** @return HasMany<AiAssistantFeedback, $this> */
    public function feedback(): HasMany
    {
        return $this->hasMany(AiAssistantFeedback::class, 'conversation_id');
    }
}
