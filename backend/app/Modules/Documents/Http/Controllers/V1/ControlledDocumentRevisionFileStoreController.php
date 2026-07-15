<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Services\ControlledDocumentImportService;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledDocumentRevisionFileStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ControlledDocument $controlledDocument,
        string $revision,
        ControlledDocumentRegistryService $registry,
        ControlledDocumentImportService $import,
    ): JsonResponse {
        abort_unless($request->user()?->can('documents:controlled:manage'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $revisionModel = $registry->findRevisionOrFail($controlledDocument, $revision);
        $updated = $import->attachFileToRevision(
            $controlledDocument,
            $revisionModel,
            $data['file'],
            $request->user(),
        );

        return $this->created([
            'id' => (string) $updated->id,
            'revision_number' => (int) $updated->revision_number,
            'original_filename' => $updated->original_filename,
            'has_file' => true,
        ]);
    }
}
