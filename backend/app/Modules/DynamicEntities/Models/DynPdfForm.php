<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DynPdfForm extends Model
{
    use HasUuids;

    protected $table = 'dyn_pdf_forms';

    protected $fillable = [
        'code',
        'name',
        'file_name',
        'file_path',
        'mime_type',
        'size_bytes',
        'catalog_code',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }
}
