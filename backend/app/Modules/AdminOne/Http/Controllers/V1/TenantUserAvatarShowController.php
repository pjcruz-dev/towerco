<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\EntraUserAvatarService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantUserAvatarShowController
{
    public function __invoke(
        Request $request,
        TenantUser $user,
        EntraUserAvatarService $avatars,
    ): StreamedResponse {
        abort_unless(
            $request->user()?->can('organization:view')
            || $request->user()?->can('organization:manage')
            || $request->user()?->can('user:manage'),
            403,
        );

        return $avatars->streamAvatar($user);
    }
}
