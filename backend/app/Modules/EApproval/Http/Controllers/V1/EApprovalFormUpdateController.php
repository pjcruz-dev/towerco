<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalForm $form, EApprovalFormService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:50'],
            'metadata_json' => ['nullable', 'array'],
            'fields' => ['required', 'array', 'min:1'],
            'steps' => ['nullable', 'array'],
            'restricted_to' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,published'],
            'schema_version' => ['nullable', 'integer', 'min:1'],
            'owner_code' => ['nullable', 'string', 'max:30'],
            'doc_type_code' => ['nullable', 'string', 'max:20'],
            'doc_no_custom_enabled' => ['nullable', 'boolean'],
            'doc_no_template' => ['nullable', 'string', 'max:120'],
            'doc_no_seq_start' => ['nullable', 'integer', 'min:1'],
            'doc_no_seq_start_rules' => ['nullable', 'array'],
            'brand_logo_url' => ['nullable', 'string', 'max:512'],
            'brand_primary_color' => ['nullable', 'string', 'max:32'],
            'related_form_ids' => ['nullable', 'string'],
            'accepts_new_submissions' => ['nullable', 'boolean'],
            'confirm_form_upgrade' => ['nullable', 'boolean'],
        ]);

        $confirmUpgrade = (bool) ($payload['confirm_form_upgrade'] ?? false);
        unset($payload['confirm_form_upgrade']);

        $result = $service->update($form, $payload, $request->user(), $confirmUpgrade);

        return $this->ok([
            'form' => $result['form']->toDetailPayload(),
            'warnings' => $result['warnings'],
        ]);
    }
}
