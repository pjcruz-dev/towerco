<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRfqBidAttachment extends Model
{
    use HasUuids;

    protected $table = 'procurement_rfq_bid_attachments';

    protected $fillable = [
        'bid_id',
        'version_id',
        'field_name',
        'file_name',
        'stored_path',
        'mime_type',
        'size_bytes',
        'uploaded_via',
        'uploaded_by_id',
    ];

    /** @return BelongsTo<ProcurementRfqBid, $this> */
    public function bid(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqBid::class, 'bid_id');
    }

    /** @return BelongsTo<ProcurementRfqBidVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProcurementRfqBidVersion::class, 'version_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'uploaded_by_id');
    }
}
