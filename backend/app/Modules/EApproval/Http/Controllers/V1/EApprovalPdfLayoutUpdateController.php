<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalPdfLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPdfLayoutUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $formId, EApprovalPdfLayoutService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $payload = $request->validate([
            'layout' => ['sometimes', 'array'],
            'template' => ['sometimes', 'array'],
            'template.template_html' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'template.template_css' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'template.orientation' => ['sometimes', 'nullable', 'string', 'in:portrait,landscape'],
            'template.footer' => ['sometimes', 'array'],
            'template.footer.appendAttachments' => ['sometimes', 'boolean'],
            'template.footer.showApprovalHistory' => ['sometimes', 'boolean'],
            'template.footer.showRequestorSignature' => ['sometimes', 'boolean'],
            'template.subsidiary_logo_field' => ['sometimes', 'nullable', 'string', 'max:80'],
            'template.subsidiary_logos' => ['sometimes', 'array'],
            'template.subsidiary_codes' => ['sometimes', 'array'],
            'template.subsidiary_codes.*' => ['sometimes', 'string', 'max:24'],
            'active_preset_id' => ['sometimes', 'string', 'max:80'],
            'presets' => ['sometimes', 'array'],
        ]);

        // Preserve full template object (nested keys beyond validated leaves).
        if ($request->exists('template') && is_array($request->input('template'))) {
            $payload['template'] = $request->input('template');
        }

        $service->save($formId, $payload, $request->user());

        return $this->ok(['ok' => true]);
    }
}
