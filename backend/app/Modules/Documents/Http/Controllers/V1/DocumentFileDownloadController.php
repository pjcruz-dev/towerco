<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentFileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentFileDownloadController extends AbstractApiController
{
    public function __invoke(Request $request, Document $document, DocumentFileStorageService $storage): StreamedResponse|JsonResponse
    {
        abort_unless($request->user()?->can('documents:view'), 403);

        $disk = (string) config('toweros.tenant_files.disk', 'tenant_files');
        if ($disk === 's3') {
            return $this->ok(['url' => $storage->downloadUrl($document)]);
        }

        return $storage->streamDownload($document);
    }
}
