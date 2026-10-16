<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentImportAnalyzeController extends AbstractApiController
{
    public function __invoke(Request $request, ControlledDocumentImportService $import): JsonResponse
    {
        abort_unless($request->user()?->can('documents:controlled:import'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:10240'],
        ]);

        return $this->ok($import->analyze($data['file']));
    }
}
