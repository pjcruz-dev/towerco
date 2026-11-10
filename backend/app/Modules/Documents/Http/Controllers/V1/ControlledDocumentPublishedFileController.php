<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentRevision;
use App\Modules\Documents\Services\ControlledDocumentStorageService;
use App\Modules\Documents\Support\ControlledDocumentPublishedFileToken;
use App\Modules\Documents\Support\ControlledDocumentRevisionStatus;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlledDocumentPublishedFileController
{
    public function __invoke(
        Request $request,
        ControlledDocument $controlledDocument,
        ControlledDocumentStorageService $storage,
    ): StreamedResponse {
        abort_unless($request->user() !== null, 401);
        abort_unless(
            ControlledDocumentPublishedFileToken::matches(
                (string) $controlledDocument->id,
                (string) $request->query('token', ''),
            ),
            403,
        );

        $revision = $this->publishedRevision($controlledDocument);
        abort_if($revision === null, 404, 'This document is not published.');

        return $storage->streamDownload($revision);
    }

    private function publishedRevision(ControlledDocument $document): ?ControlledDocumentRevision
    {
        if ((string) $document->status !== ControlledDocumentStatus::PUBLISHED) {
            return null;
        }

        $revision = $document->revisions()
            ->where('revision_number', (int) $document->current_revision)
            ->where('status', ControlledDocumentRevisionStatus::PUBLISHED)
            ->first();

        if (! $revision instanceof ControlledDocumentRevision) {
            return null;
        }

        if (! is_string($revision->stored_path) || $revision->stored_path === '') {
            return null;
        }

        return $revision;
    }
}
