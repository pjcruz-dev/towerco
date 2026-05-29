<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class TenantPermission extends SpatiePermission
{
    protected $connection = 'tenant';
}
