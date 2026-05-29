<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class TenantRole extends SpatieRole
{
    protected $connection = 'tenant';
}
