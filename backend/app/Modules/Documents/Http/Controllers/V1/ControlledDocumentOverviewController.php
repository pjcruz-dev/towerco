<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use App\Modules\Documents\Support\ControlledDocumentRoleFieldAccess;
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
        ControlledDocumentRoleFieldAccess $roleAccess,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $access->canAccessRegistry($user), 403);
        abort_unless($roleAccess->allows($user, 'view'), 403);
        abort_unless(! $roleAccess->navTargetHidden($user, '/documents/controlled'), 403);

        $data = $request->validate([
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'document_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['pending', 'published', 'obsolete'])],
            'file' => ['sometimes', 'nullable', 'string', Rule::in(['has', 'missing'])],
            'published_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'published_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:published_from'],
        ]);

        $overview = $registry->overview($user, [
            'department' => $data['department'] ?? null,
            'document_type' => $data['document_type'] ?? null,
            'status' => $data['status'] ?? null,
            'file' => $data['file'] ?? null,
            'published_from' => $data['published_from'] ?? null,
            'published_to' => $data['published_to'] ?? null,
        ]);
        $recent = is_array($overview['recent'] ?? null) ? $overview['recent'] : [];
        $overview['recent'] = array_map(
            fn (array $row): array => $roleAccess->redact($user, $row),
            $recent,
        );
        if (! $roleAccess->visible($user, 'department', 'table')) {
            $overview['by_department'] = [];
            $overview['departments'] = [];
        }
        if (! $roleAccess->visible($user, 'document_type', 'table')) {
            $overview['by_document_type'] = [];
        }
        if (! $roleAccess->visible($user, 'aging', 'table')) {
            $overview['aging'] = [];
            $overview['aging_summary'] = [];
        }

        return $this->ok($overview);
    }
}
