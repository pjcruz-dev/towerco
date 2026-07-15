<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementContract extends Model
{
    use HasUuids;

    protected $table = 'procurement_contracts';

    protected $fillable = [
        'document_no',
        'status',
        'title',
        'description',
        'vendor_id',
        'site_id',
        'primary_document_id',
        'spend_ceiling',
        'committed_po_amount',
        'currency_code',
        'effective_from',
        'end_date',
        'activated_at',
        'terminated_at',
        'terminated_by_id',
        'termination_reason',
        'owner_id',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'spend_ceiling' => 'decimal:2',
            'committed_po_amount' => 'decimal:2',
            'effective_from' => 'date',
            'end_date' => 'date',
            'activated_at' => 'datetime',
            'terminated_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    /** @return BelongsTo<ProcurementVendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ProcurementVendor::class, 'vendor_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function primaryDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'primary_document_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'owner_id');
    }

    /** @return HasMany<ProcurementContractDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ProcurementContractDocument::class, 'contract_id')->orderByDesc('linked_at');
    }

    /** @return HasMany<ProcurementPo, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(ProcurementPo::class, 'contract_id')->orderByDesc('created_at');
    }
}
