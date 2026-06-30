<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentBinderTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentBinderTemplateUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, DocumentBinderTemplateService $templates): JsonResponse
    {
        abort_unless($request->user()?->can('documents:template:manage'), 403);

        $data = $request->validate([
            'tree' => ['required', 'array'],
        ]);

        return $this->ok($templates->update($data['tree'], $request->user()));
    }
}
