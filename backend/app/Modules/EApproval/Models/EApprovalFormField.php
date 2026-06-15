<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalFormField extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'e_approval_form_fields';

    protected $fillable = [
        'form_id',
        'type',
        'name',
        'label',
        'semantic_type',
        'behavior',
        'formula',
        'validation',
        'options',
        'step_order',
    ];

    protected function casts(): array
    {
        return [
            'behavior' => 'array',
            'validation' => 'array',
            'options' => 'array',
            'step_order' => 'integer',
        ];
    }

    /** @return BelongsTo<EApprovalForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(EApprovalForm::class, 'form_id');
    }
}
