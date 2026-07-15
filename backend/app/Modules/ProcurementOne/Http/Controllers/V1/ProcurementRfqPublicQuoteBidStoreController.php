<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementRfqPublicBidService;
use App\Modules\ProcurementOne\Services\ProcurementRfqVendorInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementRfqPublicQuoteBidStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $token,
        ProcurementRfqVendorInvitationService $invitations,
        ProcurementRfqPublicBidService $quotes,
    ): JsonResponse {
        $invitation = $invitations->resolveActiveInvitation($token);

        $validated = $request->validate([
            'contact_name' => ['required', 'string', 'max:255'],
            'validity_until' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'currency_code' => ['sometimes', 'string', 'max:8'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.rfq_line_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['sometimes', 'numeric', 'min:0'],
            'lines.*.monthly_unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.yearly_unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.lead_time_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'lines.*.notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $attachmentFiles = array_values(array_filter(
            $request->file('attachments', []) ?? [],
            static fn ($file) => $file instanceof \Illuminate\Http\UploadedFile,
        ));

        $result = $quotes->submit($invitation, $validated, $attachmentFiles);

        return $this->created($result);
    }
}
