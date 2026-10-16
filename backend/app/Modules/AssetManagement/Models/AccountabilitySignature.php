<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class AccountabilitySignature extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'accountability_signatures';

    protected $fillable = ['accountability_id', 'signed_by', 'signature', 'signed_at'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }
}
