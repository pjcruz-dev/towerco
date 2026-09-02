<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynPdfForm;
use App\Modules\DynamicEntities\Services\DynPdfFormStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynPdfFormDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynPdfForm $form,
        DynPdfFormStorageService $storage,
    ): JsonResponse {
        abort_unless($request->user()?->can('printables:manage'), 403);

        $storage->destroy($form);

        return $this->ok(['deleted' => true]);
    }
}
