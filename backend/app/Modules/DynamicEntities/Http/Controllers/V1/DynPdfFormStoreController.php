<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynPdfFormStorageService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynPdfFormStoreController extends AbstractApiController
{
    public function __invoke(Request $request, DynPdfFormStorageService $storage): JsonResponse
    {
        abort_unless($request->user()?->can('printables:manage'), 403);

        $maxKb = max(1, (int) config('toweros.tenant_files.max_size_kb', 10240));
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb],
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $form = $storage->store(
            $data['file'],
            $actor,
            isset($data['code']) ? (string) $data['code'] : null,
            isset($data['name']) ? (string) $data['name'] : null,
        );

        return $this->ok([
            'id' => $form->id,
            'code' => $form->code,
            'name' => $form->name,
            'file_name' => $form->file_name,
            'size_bytes' => $form->size_bytes,
            'preview_url' => '/api/v1/dynamic-entities/pdf-forms/'.$form->id.'/file',
        ], 201);
    }
}
