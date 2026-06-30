<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementVendorAccreditationEvent extends Model
{
    use HasUuids;

    protected $table = 'procurement_vendor_accreditation_events';

    public $timestamps = false;

    protected $fillable = [
        'vendor_id',
        'status_from',
        'status_to',
        'reason',
        'actor_user_id',
        'submission_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProcurementVendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ProcurementVendor::class, 'vendor_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'actor_user_id');
    }
}
