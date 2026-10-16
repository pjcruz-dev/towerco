<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AssetDocument extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'asset_documents';

    protected $fillable = [
        'asset_id', 'repair_id', 'disposal_id', 'file_path', 'file_name', 'mime_type', 'size_bytes', 'uploaded_by',
    ];
}
