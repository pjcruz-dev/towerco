<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ControlledDocumentOverviewController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ControlledDocumentRegistryService $registry,
        ControlledDocumentAccessService $access,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $access->canAccessRegistry($user), 403);

        $data = $request->validate([
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'document_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['pending', 'published', 'obsolete'])],
            'file' => ['sometimes', 'nullable', 'string', Rule::in(['has', 'missing'])],
        ]);

        return $this->ok($registry->overview($user, [
            'department' => $data['department'] ?? null,
            'document_type' => $data['document_type'] ?? null,
            'status' => $data['status'] ?? null,
            'file' => $data['file'] ?? null,
        ]));
    }
}
