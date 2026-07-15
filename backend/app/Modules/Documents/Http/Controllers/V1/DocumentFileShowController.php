<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentFileShowController extends AbstractApiController
{
    public function __invoke(Request $request, Document $document, DocumentService $documents): JsonResponse
    {
        abort_unless($request->user()?->can('documents:view'), 403);

        return $this->ok($documents->detail($document));
    }
}
