<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalFormImportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormImportController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalFormImportExportService $import): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $result = $import->import($request->all(), $request->user());

        return $this->created([
            'id' => (string) $result['form']->id,
            'warnings' => $result['warnings'],
        ]);
    }
}
