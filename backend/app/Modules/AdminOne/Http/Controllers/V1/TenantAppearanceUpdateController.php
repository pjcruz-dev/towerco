<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantAppearanceService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantAppearanceUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, TenantAppearanceService $service): JsonResponse
    {
        abort_unless($request->user()?->can('tenant:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'primary_preset' => ['nullable', 'string', 'in:violet,teal,rose,amber,blue,custom'],
            'primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'skin' => ['required', 'string', 'in:default,bordered'],
            'layout' => ['required', 'string', 'in:vertical,collapsed,horizontal'],
            'content' => ['required', 'string', 'in:compact,wide'],
        ]);

        return $this->ok($service->update($data, $actor));
    }
}
