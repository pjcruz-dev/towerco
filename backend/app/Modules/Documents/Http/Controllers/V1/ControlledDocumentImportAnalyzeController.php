<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentImportService;
use App\Modules\Documents\Support\ControlledDocumentRoleFieldAccess;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentImportAnalyzeController extends AbstractApiController
{
    public function __invoke(Request $request, ControlledDocumentImportService $import): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('documents:controlled:import'), 403);
        abort_unless(app(ControlledDocumentRoleFieldAccess::class)->allows($user, 'import'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:10240'],
        ]);

        return $this->ok($import->analyze($data['file']));
    }
}
