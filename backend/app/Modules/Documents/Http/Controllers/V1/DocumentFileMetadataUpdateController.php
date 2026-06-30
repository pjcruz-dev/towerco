<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Documents\Support\DocumentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentFileMetadataUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, Document $document, DocumentService $documents): JsonResponse
    {
        abort_unless($request->user()?->can('documents:upload'), 403);

        $payload = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:'.DocumentStatus::DRAFT.','.DocumentStatus::FINAL.','.DocumentStatus::SUPERSEDED],
            'expires_at' => ['nullable', 'date'],
        ]);

        return $this->ok($documents->updateMetadata($document, $payload, $request->user()));
    }
}
