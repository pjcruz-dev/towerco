<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementVendorAccreditationService;
use App\Modules\ProcurementOne\Services\ProcurementVendorRegistryService;
use App\Modules\ProcurementOne\Support\ProcurementVendorAccreditationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementVendorAccreditationUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $vendor,
        ProcurementVendorRegistryService $registry,
        ProcurementVendorAccreditationService $accreditation,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:vendors:manage'), 403);

        $model = $registry->find($vendor);
        abort_if($model === null, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ProcurementVendorAccreditationStatus::all())],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'accreditation_expires_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $expiresAt = isset($data['accreditation_expires_at'])
            ? new \DateTimeImmutable((string) $data['accreditation_expires_at'])
            : null;

        $updated = $accreditation->transition(
            $model,
            (string) $data['status'],
            isset($data['reason']) ? trim((string) $data['reason']) : null,
            $actor,
            null,
            $data['status'] === ProcurementVendorAccreditationStatus::ACCREDITED ? now() : null,
            $expiresAt,
        );

        return $this->ok($registry->toDetailPayload($updated->load(['accreditationEvents', 'documents'])));
    }
}
