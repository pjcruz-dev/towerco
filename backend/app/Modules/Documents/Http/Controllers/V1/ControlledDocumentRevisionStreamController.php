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
        if ((string) $revisionModel->status === ControlledDocumentRevisionStatus::SUPERSEDED) {
            $user = $request->user();
            abort_unless(
                $user?->can('documents:controlled:manage')
                || $user?->can('documents:controlled:approve')
                || $user?->can('documents:controlled:import'),
                403,
            );
        }

        return $storage->streamDownload($revisionModel);
    }
}
