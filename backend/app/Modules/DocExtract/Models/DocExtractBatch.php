<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DocExtractBatch extends Model
{
    use HasUuids;

    protected $table = 'doc_extract_batches';

    protected $fillable = [
        'template_id',
        'status',
        'document_count',
        'ready_count',
        'failed_count',
        'message',
        'field_schema',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'field_schema' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocExtractTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocExtractDocument::class, 'batch_id');
    }
}
