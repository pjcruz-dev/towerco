<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalUserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EApprovalMeSignatureUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalUserProfileService $profiles): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        $data = $request->validate([
            'signature' => ['nullable', 'string', 'max:500000'],
            'signature_consent' => ['sometimes', 'boolean'],
            'signature_storage_consent' => ['sometimes', 'boolean'],
        ]);

        $signature = $data['signature'] ?? null;
        $hasSignature = is_string($signature) && trim($signature) !== '';
        if ($hasSignature && ! ($data['signature_consent'] ?? false)) {
            throw ValidationException::withMessages([
                'signature_consent' => [__('You must accept the electronic signature consent before saving a signature.')],
            ]);
        }
        if ($hasSignature && ! ($data['signature_storage_consent'] ?? false)) {
            throw ValidationException::withMessages([
                'signature_storage_consent' => [__('You must consent to storing your signature image before saving.')],
            ]);
        }

        $profiles->updateSignature($request->user(), $signature, $request->user());

        return $this->ok();
    }
}
