<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalFormValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormValidateController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalFormValidator $validator): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fields' => ['required', 'array', 'min:1'],
            'steps' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:draft,published'],
        ]);

        $strict = ($payload['status'] ?? 'draft') === 'published';
        $warnings = $validator->validate($payload, $strict);

        return $this->ok(['ok' => true, 'warnings' => $warnings]);
    }
}
