<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementVendorDocument extends Model
{
    use HasUuids;

    protected $table = 'procurement_vendor_documents';

    protected $fillable = [
        'vendor_id',
        'document_id',
        'e_approval_attachment_id',
        'document_kind',
        'label',
        'file_name',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProcurementVendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ProcurementVendor::class, 'vendor_id');
    }
}
