<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Modules\DynamicEntities\Models\DynPdfForm;
use App\Modules\DynamicEntities\Services\DynPdfFormStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DynPdfFormDownloadController
{
    public function __invoke(
        Request $request,
        DynPdfForm $form,
        DynPdfFormStorageService $storage,
    ): StreamedResponse {
        abort_unless($request->user()?->can('printables:manage'), 403);

        return $storage->download($form);
    }
}
