<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUiPreference extends BaseModel
{
    protected $table = 'user_ui_preferences';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'preference_key',
        'value_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }
}
