<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentRegisterAccessService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ControlledDocumentRegisterAccessUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, ControlledDocumentRegisterAccessService $access): JsonResponse
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        abort_unless($user?->can('documents:controlled:manage'), 403);

        $data = $request->validate([
            'viewer_roles' => ['nullable', 'array'],
            'viewer_roles.*' => ['string', 'max:120'],
            'full_access_roles' => ['nullable', 'array'],
            'full_access_roles.*' => ['string', 'max:120'],
            'own_only_roles' => ['nullable', 'array'],
            'own_only_roles.*' => ['string', 'max:120'],
            'role_department_map' => ['nullable', 'array'],
            'role_department_map.*' => ['array'],
            'role_department_map.*.*' => ['string', 'max:120'],
        ]);

        return $this->ok($access->update($data, $user));
    }
}
