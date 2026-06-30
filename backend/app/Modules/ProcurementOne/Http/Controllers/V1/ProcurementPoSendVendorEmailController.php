<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementDocumentLifecycleService;
use App\Modules\ProcurementOne\Services\ProcurementPoRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPoVendorNotificationService;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPoSendVendorEmailController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $po,
        ProcurementPoVendorNotificationService $vendorMail,
        ProcurementDocumentLifecycleService $lifecycle,
        ProcurementPoRegistryService $registry,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'event' => ['sometimes', 'string', 'in:po_approved,po_sent'],
        ]);

        $model = $registry->find($po);
        abort_if($model === null, 404);

        $event = (string) ($data['event'] ?? 'po_sent');
        if ($event === 'po_sent' && (string) $model->status === ProcurementPoStatus::APPROVED) {
            $model = $lifecycle->markPurchaseOrderSent($model, $actor);
        }

        $sent = $vendorMail->sendManual($model, $actor, $event);
        abort_unless($sent, 422, __('Vendor email could not be sent. Check vendor contact email and template settings.'));

        $fresh = $registry->find((string) $model->id);

        return $this->ok([
            'po' => $registry->toDetailPayload($fresh ?? $model),
            'sent' => true,
        ]);
    }
}
