<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentFileVersionStoreController extends AbstractApiController
{
    public function __invoke(Request $request, Document $document, DocumentService $documents): JsonResponse
    {
        abort_unless($request->user()?->can('documents:upload'), 403);

        $payload = $request->validate([
            'file' => ['required', 'file'],
        ]);

        return $this->ok($documents->uploadNewVersion($document, $request->file('file'), $request->user()));
    }
}
