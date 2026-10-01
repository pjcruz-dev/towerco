<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Models;

use App\Modules\AdminOne\Support\RoleAccessMatrix;
use Spatie\Permission\Models\Role as SpatieRole;

class TenantRole extends SpatieRole
{
    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'guard_name',
        'access_matrix_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_matrix_json' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accessMatrix(): array
    {
        return RoleAccessMatrix::normalize(
            is_array($this->access_matrix_json) ? $this->access_matrix_json : null,
        );
    }
}
