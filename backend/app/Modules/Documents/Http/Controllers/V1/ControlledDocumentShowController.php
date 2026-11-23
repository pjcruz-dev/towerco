<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Services\ControlledDocumentAccessService;
use App\Modules\Documents\Services\ControlledDocumentFormResolverService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use App\Modules\Documents\Support\ControlledDocumentRoleFieldAccess;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ControlledDocument $controlledDocument,
        ControlledDocumentRegistryService $registry,
        ControlledDocumentAccessService $access,
        ControlledDocumentFormResolverService $forms,
        ControlledDocumentRoleFieldAccess $roleAccess,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $access->canViewDocument($user, $controlledDocument), 403);
        abort_unless($roleAccess->allows($user, 'view'), 403);
        abort_unless(! $roleAccess->navTargetHidden($user, '/documents/controlled/register'), 403);

        $payload = $registry->show($controlledDocument);
        $payload['revision_form_id'] = $controlledDocument->e_approval_form_id
            ?: $forms->resolveRevisionFormFor($user)?->id;
        $payload = $roleAccess->redact($user, $payload, 'record');
        $revisions = is_array($payload['revisions'] ?? null) ? $payload['revisions'] : [];
        $payload['revisions'] = array_map(
            fn (array $revision): array => $roleAccess->redact($user, $revision, 'record'),
            $revisions,
        );

        return $this->ok($payload);
    }
}
