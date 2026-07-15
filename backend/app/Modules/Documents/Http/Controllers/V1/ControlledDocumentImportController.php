<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\ControlledDocumentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentImportController extends AbstractApiController
{
    public function __invoke(Request $request, ControlledDocumentImportService $import): JsonResponse
    {
        abort_unless($request->user()?->can('documents:controlled:import'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        return $this->ok($import->importCsv($data['file'], $request->user()));
    }
}
