<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RolloutCustomPhase extends Model
{
    use HasUuids;

    public const ANCHOR_ENDORSEMENT = 'endorsement';

    public const ANCHOR_TSSR_APPROVED = 'tssr_approved';

    /** @var list<string> */
    public const TEMPLATE_KEYS = ['bts', 'rtb', 'colocation'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'phase_key',
        'label',
        'description',
        'owner_role',
        'default_anchor',
        'default_working_day_start',
        'default_working_day_end',
        'default_gate',
        'counts_toward_sla',
        'applicable_templates',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_working_day_start' => 'integer',
            'default_working_day_end' => 'integer',
            'counts_toward_sla' => 'boolean',
            'applicable_templates' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
