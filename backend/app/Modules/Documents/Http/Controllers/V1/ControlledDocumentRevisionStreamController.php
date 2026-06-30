<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Services\ControlledDocumentRegistryService;
use App\Modules\Documents\Services\ControlledDocumentStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlledDocumentRevisionStreamController
{
    public function __invoke(
        Request $request,
        ControlledDocument $controlledDocument,
        string $revision,
        ControlledDocumentRegistryService $registry,
        ControlledDocumentStorageService $storage,
    ): StreamedResponse {
        abort_unless($request->user()?->can('documents:controlled:view'), 403);

        $revisionModel = $registry->findRevisionOrFail($controlledDocument, $revision);

        return $storage->streamDownload($revisionModel);
    }
}
