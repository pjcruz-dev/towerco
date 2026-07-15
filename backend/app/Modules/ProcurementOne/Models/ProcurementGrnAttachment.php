<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementGrnAttachment extends Model
{
    use HasUuids;

    protected $table = 'procurement_grn_attachments';

    protected $fillable = [
        'grn_id',
        'field_name',
        'file_name',
        'stored_path',
        'mime_type',
        'size_bytes',
        'uploaded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<ProcurementGrn, $this> */
    public function grn(): BelongsTo
    {
        return $this->belongsTo(ProcurementGrn::class, 'grn_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'uploaded_by_id');
    }
}
