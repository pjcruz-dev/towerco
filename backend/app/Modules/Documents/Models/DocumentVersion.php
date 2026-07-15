<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'document_versions';

    protected $fillable = [
        'document_id',
        'version',
        'original_filename',
        'stored_path',
        'mime_type',
        'size_bytes',
        'uploaded_by_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'uploaded_by_id');
    }
}
