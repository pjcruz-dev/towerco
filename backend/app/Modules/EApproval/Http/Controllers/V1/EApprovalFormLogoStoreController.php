<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormLogoStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalForm $form,
        EApprovalFileStorageService $storage,
        EApprovalAuditLogger $audit,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $data = $request->validate([
            'file' => ['required', 'file'],
        ]);

        $result = $storage->storeFormLogo($form, $data['file']);
        $form->brand_logo_url = $result['brand_logo_url'];
        $form->save();

        $audit->log('form_logo_updated', $form->id, $form->brand_logo_url, $request->user());

        return $this->ok(['brand_logo_url' => $form->brand_logo_url]);
    }
}
