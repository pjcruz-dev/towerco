<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Listeners;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Events\ProcurementDocumentApproved;
use App\Modules\ProcurementOne\Events\ProcurementDocumentCancelled;
use App\Modules\ProcurementOne\Events\ProcurementDocumentVoided;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Services\ProcurementPoVendorNotificationService;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;

final class ProcurementDocumentLifecycleListener
{
    public function __construct(
        private readonly ProcurementPoVendorNotificationService $vendorMail,
    ) {}

    public function handleApproved(ProcurementDocumentApproved $event): void
    {
        if ($event->documentType !== ProcurementDocumentType::PURCHASE_ORDER) {
            return;
        }

        $po = ProcurementPo::query()->find($event->documentId);
        if ($po === null) {
            return;
        }

        $actor = $event->actorUserId !== null
            ? TenantUser::query()->find($event->actorUserId)
            : null;

        if ($actor instanceof TenantUser) {
            $this->vendorMail->maybeAutoDispatch($po, 'po_approved', $actor);
        }
    }

    public function handleCancelled(ProcurementDocumentCancelled $event): void
    {
        // Vendor cancellation emails are dispatched directly from lifecycle service to include reason.
    }

    public function handleVoided(ProcurementDocumentVoided $event): void
    {
        // Vendor void emails are dispatched directly from lifecycle service to include reason.
    }
}
