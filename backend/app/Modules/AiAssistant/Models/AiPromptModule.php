<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiPromptModule extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'ai_prompt_modules';

    protected $fillable = [
        'key',
        'name',
        'filename',
        'description',
        'kind',
        'intent_key',
        'sort_order',
        'body',
        'is_enabled',
        'is_system',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
