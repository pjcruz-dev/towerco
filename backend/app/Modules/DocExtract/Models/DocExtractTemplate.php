<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Models;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\DocExtract\Support\DocExtractTemplateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DocExtractTemplate extends Model
{
    use HasUuids;

    protected $table = 'doc_extract_templates';

    protected $fillable = [
        'name',
        'description',
        'fields',
        'status',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
        ];
    }

    public function isPublished(): bool
    {
        return DocExtractTemplateStatus::isPublished($this->status);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DocExtractBatch::class, 'template_id');
    }
}
