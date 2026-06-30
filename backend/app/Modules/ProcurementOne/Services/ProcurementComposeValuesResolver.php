<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementLineGridColumns;

final class ProcurementComposeValuesResolver
{
    public function __construct(
        private readonly ProcurementPrValueMapper $prMapper,
        private readonly ProcurementPoValueMapper $poMapper,
        private readonly ProcurementApInvoiceValueMapper $apMapper,
        private readonly ProcurementPoFormResolverService $poFormResolver,
        private readonly ProcurementApInvoiceFormResolverService $apFormResolver,
    ) {}

    /**
     * @return array<string, string>
     */
    public function forPurchaseRequisition(ProcurementPr $pr): array
    {
        if ($pr->e_approval_submission_id !== null) {
            $fromSubmission = $this->fromSubmission((string) $pr->e_approval_submission_id);
            if ($fromSubmission !== []) {
                return $fromSubmission;
            }
        }

        $lines = $pr->lines()->get()->map(static fn ($line) => ProcurementLineGridColumns::prLineArray($line))->all();

        return $this->stringifyValues($this->prMapper->toEApprovalValues($pr, $lines));
    }

    /**
     * @return array<string, string>
     */
    public function forPurchaseOrder(ProcurementPo $po): array
    {
        if ($po->e_approval_submission_id !== null) {
            $fromSubmission = $this->fromSubmission((string) $po->e_approval_submission_id);
            if ($fromSubmission !== []) {
                return $fromSubmission;
            }
        }

        return $this->stringifyValues(
            $this->poMapper->toEApprovalValues($po, $this->poFormResolver->primaryPrDocumentNo($po)),
        );
    }

    /**
     * @return array<string, string>
     */
    public function forApInvoice(ProcurementApInvoice $invoice): array
    {
        if ($invoice->e_approval_submission_id !== null) {
            $fromSubmission = $this->fromSubmission((string) $invoice->e_approval_submission_id);
            if ($fromSubmission !== []) {
                return $fromSubmission;
            }
        }

        $invoice->loadMissing('purchaseOrder');

        return $this->stringifyValues(
            $this->apMapper->toEApprovalValues($invoice, $invoice->purchaseOrder?->document_no),
        );
    }

    /**
     * @return array<string, string>
     */
    private function fromSubmission(string $submissionId): array
    {
        /** @var EApprovalSubmission|null $submission */
        $submission = EApprovalSubmission::query()
            ->with('values.field')
            ->find($submissionId);

        if ($submission === null) {
            return [];
        }

        $values = [];
        foreach ($submission->values as $formValue) {
            $name = (string) ($formValue->field?->name ?? '');
            if ($name === '') {
                continue;
            }

            $values[$name] = (string) ($formValue->value ?? '');
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function stringifyValues(array $values): array
    {
        $stringified = [];
        foreach ($values as $name => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $stringified[(string) $name] = (string) json_encode($value, JSON_THROW_ON_ERROR);

                continue;
            }

            if (is_bool($value)) {
                $stringified[(string) $name] = $value ? 'true' : 'false';

                continue;
            }

            $stringified[(string) $name] = trim((string) $value);
        }

        return $stringified;
    }
}
