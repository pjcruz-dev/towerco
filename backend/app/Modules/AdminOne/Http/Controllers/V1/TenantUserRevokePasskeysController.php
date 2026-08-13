<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\WebAuthnPasskeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantUserRevokePasskeysController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TenantUser $user,
        WebAuthnPasskeyService $passkeys,
    ): JsonResponse {
        abort_unless($request->user()?->can('user:manage'), 403);

        /** @var TenantUser $actor */
        $actor = $request->user();
        $revoked = $passkeys->revokeAllForUser($actor, $user);

        return $this->ok([
            'message' => __('All passkeys revoked for this user.'),
            'revoked_count' => $revoked,
        ]);
    }
}
