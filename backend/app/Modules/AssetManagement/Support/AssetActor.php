<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Support;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\Request;

final class AssetActor
{
    public static function from(Request $request): TenantUser
    {
        $user = $request->user();
        if (! $user instanceof TenantUser) {
            abort(401);
        }

        return $user;
    }
}
