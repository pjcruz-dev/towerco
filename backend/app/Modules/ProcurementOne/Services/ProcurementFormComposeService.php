<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Services\EApprovalSubmissionValuesValidator;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use Illuminate\Validation\ValidationException;

final class ProcurementFormComposeService
{
    public function __construct(
        private readonly ProcurementPrFormResolverService $prFormResolver,
        private readonly ProcurementPoFormResolverService $poFormResolver,
        private readonly ProcurementApInvoiceFormResolverService $apFormResolver,
        private readonly EApprovalSubmissionValuesValidator $valuesValidator,
        private readonly ProcurementFormValuesProjector $projector,
        private readonly ProcurementPrService $prService,
        private readonly ProcurementPoService $poService,
        private readonly ProcurementApInvoiceService $apInvoiceService,
        private readonly ProcurementApInvoiceSubmissionBridgeService $apBridge,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function createPurchaseRequisition(array $values, TenantUser $actor, bool $requireRequired = false): ProcurementPr
    {
        $form = $this->prFormResolver->resolvePublishedFormOrFail();
        $this->valuesValidator->validate($form, $values, $requireRequired);

        return $this->prService->create(
            $this->projector->projectPurchaseRequisition($values, $form),
            $actor,
            $values,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updatePurchaseRequisition(
        ProcurementPr $pr,
        array $values,
        TenantUser $actor,
        bool $requireRequired = false,
    ): ProcurementPr {
        $form = $this->prFormResolver->resolvePublishedFormOrFail();
        $this->valuesValidator->validate($form, $values, $requireRequired);

        return $this->prService->update(
            $pr,
            $this->projector->projectPurchaseRequisition($values, $form),
            $actor,
            $values,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function createPurchaseOrder(
        array $values,
        TenantUser $actor,
        ?string $parentSubmissionId = null,
        ?ProcurementPr $fromPr = null,
        bool $requireRequired = false,
    ): ProcurementPo {
        $form = $this->poFormResolver->resolvePublishedFormOrFail();
        $this->valuesValidator->validate($form, $values, $requireRequired);

        $prIds = $fromPr !== null
            ? [(string) $fromPr->id]
            : $this->projector->resolvePrIdsFromParentSubmission($parentSubmissionId);

        if ($prIds === []) {
            throw ValidationException::withMessages([
                'parent_submission_id' => [__('Select an approved purchase requisition before creating a purchase order.')],
            ]);
        }

        $projected = $this->projector->projectPurchaseOrder($values, $prIds, $form);

        if ($fromPr !== null) {
            return $this->poService->createFromPurchaseRequisition($fromPr, $projected, $actor, $values);
        }

        return $this->poService->create($projected, $actor, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updatePurchaseOrder(
        ProcurementPo $po,
        array $values,
        TenantUser $actor,
        bool $requireRequired = false,
    ): ProcurementPo {
        $form = $this->poFormResolver->resolvePublishedFormOrFail();
        $this->valuesValidator->validate($form, $values, $requireRequired);

        $projected = $this->projector->projectPurchaseOrder($values, [], $form);
        unset($projected['pr_ids']);
        if (($projected['lines'] ?? null) === null) {
            unset($projected['lines']);
        }

        return $this->poService->update($po, $projected, $actor, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{invoice: ProcurementApInvoice, warning: string|null}
     */
    public function createApInvoiceFromPurchaseOrder(
        ProcurementPo $po,
        array $values,
        TenantUser $actor,
        bool $requireRequired = false,
    ): array {
        $form = $this->apFormResolver->resolvePublishedFormOrFail();
        $this->valuesValidator->validate($form, $values, $requireRequired);

        $result = $this->apInvoiceService->createFromPurchaseOrder(
            $po,
            $this->projector->projectApInvoice($values, $po, $form),
            $actor,
            $values,
        );

        $this->apBridge->ensureDraftSubmission($result['invoice'], $actor);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateApInvoice(
        ProcurementApInvoice $invoice,
        array $values,
        TenantUser $actor,
        bool $requireRequired = false,
    ): ProcurementApInvoice {
        $form = $this->apFormResolver->resolvePublishedFormOrFail();
        $this->valuesValidator->validate($form, $values, $requireRequired);

        $po = $invoice->purchaseOrder()->with('lines')->firstOrFail();
        $projected = $this->projector->projectApInvoice($values, $po, $form);
        if (($projected['lines'] ?? null) === null) {
            unset($projected['lines']);
        }
        $updated = $this->apInvoiceService->updateDraft(
            $invoice,
            $projected,
            $actor,
            $values,
        );

        $this->apBridge->syncDraft($updated, $actor);

        return $updated;
    }
}
