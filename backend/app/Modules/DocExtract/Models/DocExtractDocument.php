<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocExtractDocument extends Model
{
    use HasUuids;

    protected $table = 'doc_extract_documents';

    protected $fillable = [
        'batch_id',
        'template_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'size_bytes',
        'status',
        'scan_engine',
        'extracted_text',
        'field_values',
        'scan_meta',
        'error_message',
        'purged_at',
    ];

    protected function casts(): array
    {
        return [
            'field_values' => 'array',
            'scan_meta' => 'array',
            'purged_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DocExtractBatch::class, 'batch_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocExtractTemplate::class, 'template_id');
    }
}
