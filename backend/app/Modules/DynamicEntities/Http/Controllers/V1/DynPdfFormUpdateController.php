<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynPdfForm;
use App\Modules\DynamicEntities\Services\DynPdfFormStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynPdfFormUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynPdfForm $form,
        DynPdfFormStorageService $storage,
    ): JsonResponse {
        abort_unless($request->user()?->can('printables:manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
        ]);

        $updated = $storage->rename(
            $form,
            (string) $data['name'],
            array_key_exists('code', $data) ? (string) $data['code'] : null,
        );

        return $this->ok([
            'id' => $updated->id,
            'code' => $updated->code,
            'name' => $updated->name,
            'file_name' => $updated->file_name,
        ]);
    }
}
