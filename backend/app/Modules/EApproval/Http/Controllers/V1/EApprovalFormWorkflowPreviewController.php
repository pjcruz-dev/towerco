<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalConditionalWorkflowCompilerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormWorkflowPreviewController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalForm $form,
        EApprovalConditionalWorkflowCompilerService $compiler,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $request->validate([
            'values' => ['nullable', 'array'],
            'requestor_email' => ['nullable', 'email', 'max:255'],
        ]);

        /** @var array<string, mixed> $values */
        $values = is_array($request->input('values')) ? $request->input('values') : [];
        $requestorEmail = $request->input('requestor_email');

        return $this->ok($compiler->preview(
            $form,
            $values,
            is_string($requestorEmail) ? $requestorEmail : null,
        ));
    }
}
