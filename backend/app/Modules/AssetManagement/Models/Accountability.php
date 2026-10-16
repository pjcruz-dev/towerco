<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Accountability extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'accountability';

    protected $fillable = [
        'number', 'employee_id', 'status', 'created_by', 'sent_by', 'sent_at',
        'reminder_count', 'last_reminded_at', 'escalated_at', 'signed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'escalated_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    /** @return HasMany<AccountabilityItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AccountabilityItem::class, 'accountability_id')->orderBy('sort_order');
    }

    /** @return HasMany<AccountabilitySignature, $this> */
    public function signatures(): HasMany
    {
        return $this->hasMany(AccountabilitySignature::class, 'accountability_id');
    }
}
