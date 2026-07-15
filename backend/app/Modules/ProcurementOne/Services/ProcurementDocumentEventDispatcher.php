<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Events\ProcurementDocumentApproved;
use App\Modules\ProcurementOne\Events\ProcurementDocumentCancelled;
use App\Modules\ProcurementOne\Events\ProcurementDocumentSubmitted;
use App\Modules\ProcurementOne\Events\ProcurementDocumentVoided;

/**
 * Central dispatcher for procurement document lifecycle hooks (listeners, projections, integrations).
 */
final class ProcurementDocumentEventDispatcher
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function approved(
        string $documentType,
        string $documentId,
        ?string $documentNo = null,
        ?string $actorUserId = null,
        array $metadata = [],
    ): ProcurementDocumentApproved {
        $event = new ProcurementDocumentApproved($documentType, $documentId, $documentNo, $actorUserId, $metadata);
        event($event);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function voided(
        string $documentType,
        string $documentId,
        ?string $documentNo = null,
        ?string $actorUserId = null,
        array $metadata = [],
    ): ProcurementDocumentVoided {
        $event = new ProcurementDocumentVoided($documentType, $documentId, $documentNo, $actorUserId, $metadata);
        event($event);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function cancelled(
        string $documentType,
        string $documentId,
        ?string $documentNo = null,
        ?string $actorUserId = null,
        array $metadata = [],
    ): ProcurementDocumentCancelled {
        $event = new ProcurementDocumentCancelled($documentType, $documentId, $documentNo, $actorUserId, $metadata);
        event($event);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function submitted(
        string $documentType,
        string $documentId,
        ?string $documentNo = null,
        ?string $actorUserId = null,
        array $metadata = [],
    ): ProcurementDocumentSubmitted {
        $event = new ProcurementDocumentSubmitted($documentType, $documentId, $documentNo, $actorUserId, $metadata);
        event($event);

        return $event;
    }
}
