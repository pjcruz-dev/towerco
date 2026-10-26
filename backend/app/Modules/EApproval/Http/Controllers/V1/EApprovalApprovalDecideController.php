<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Services\ApprovalDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EApprovalApprovalDecideController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalRequestApproval $approval,
        ApprovalDecisionService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:approve'), 403);

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected,acknowledged'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'signature' => ['nullable', 'string', 'max:500000'],
            'signature_consent' => ['sometimes', 'boolean'],
            'signature_storage_consent' => ['sometimes', 'boolean'],
            'release_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'release_date' => ['sometimes', 'nullable', 'date'],
            'settlement_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'proceed_optional' => ['sometimes', 'boolean'],
            'file_audience' => ['sometimes', 'nullable', 'string', 'in:everyone,specific_person,selected_users'],
            'file_recipient_ids' => ['sometimes', 'array', 'max:100'],
            'file_recipient_ids.*' => ['uuid'],
        ]);

        if ($data['decision'] === 'approved' || $data['decision'] === 'acknowledged') {
            $signature = $data['signature'] ?? null;
            $hasSignature = is_string($signature) && trim($signature) !== '';
            if ($data['decision'] === 'acknowledged' && ! $hasSignature) {
                throw ValidationException::withMessages([
                    'signature' => [__('Add your signature before reviewing.')],
                ]);
            }
            if ($hasSignature && ! ($data['signature_consent'] ?? false)) {
                throw ValidationException::withMessages([
                    'signature_consent' => [__('You must accept the electronic signature consent before confirming.')],
                ]);
            }
            if ($hasSignature && ! ($data['signature_storage_consent'] ?? false)) {
                throw ValidationException::withMessages([
                    'signature_storage_consent' => [__('You must consent to storing your signature image before confirming.')],
                ]);
            }
        }

        $updated = $service->decide(
            $approval,
            $data['decision'],
            $data['remarks'] ?? null,
            $data['signature'] ?? null,
            $request->user(),
            [
                'release_reference' => $data['release_reference'] ?? null,
                'release_date' => $data['release_date'] ?? null,
                'settlement_reference' => $data['settlement_reference'] ?? null,
            ],
            (bool) ($data['proceed_optional'] ?? false),
            isset($data['file_audience']) ? (string) $data['file_audience'] : null,
            array_values(array_map(static fn ($id): string => (string) $id, $data['file_recipient_ids'] ?? [])),
        );

        return $this->ok(['approval' => $updated->toListRow()]);
    }
}
