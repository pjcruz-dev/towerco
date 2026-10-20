<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;

class AiAssistantSetting extends Model
{
    public const SINGLETON_ID = 1;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $table = 'ai_assistant_settings';

    protected $fillable = [
        'id',
        'llm_provider',
        'cursor_api_key',
        'gemini_api_key',
        'openai_api_key',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'cursor_api_key' => 'encrypted',
            'gemini_api_key' => 'encrypted',
            'openai_api_key' => 'encrypted',
        ];
    }
}
