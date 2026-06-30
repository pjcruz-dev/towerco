<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Models;

use App\Modules\Documents\Models\Document;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementContractDocument extends Model
{
    use HasUuids;

    protected $table = 'procurement_contract_documents';

    protected $fillable = [
        'contract_id',
        'document_id',
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

    /** @return BelongsTo<ProcurementContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(ProcurementContract::class, 'contract_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
