<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiMessage extends Model
{
    use HasUuids;

    protected $table = 'ai_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'citations',
        'model_name',
        'prompt_tokens',
        'completion_tokens',
        'latency_ms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    /** @return HasMany<AiAssistantFeedback, $this> */
    public function feedback(): HasMany
    {
        return $this->hasMany(AiAssistantFeedback::class, 'message_id');
    }
}
