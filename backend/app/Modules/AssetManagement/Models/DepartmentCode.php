<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Models;

use App\Core\Models\BaseModel;

class DepartmentCode extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'department_codes';

    protected $fillable = ['department_name', 'code'];
}
