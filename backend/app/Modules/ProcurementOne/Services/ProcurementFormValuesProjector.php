<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementFormGridColumnResolver;
use App\Modules\ProcurementOne\Support\ProcurementOptionalForeignKeyNormalizer;
use Illuminate\Validation\ValidationException;

final class ProcurementFormValuesProjector
{
    public function __construct(
        private readonly ProcurementPrValueMapper $prMapper,
        private readonly ProcurementPoValueMapper $poMapper,
        private readonly ProcurementApInvoiceValueMapper $apMapper,
        private readonly ProcurementFormGridColumnResolver $gridColumns,
        private readonly ProcurementOptionalForeignKeyNormalizer $foreignKeys,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function projectPurchaseRequisition(array $values, ?EApprovalForm $form = null): array
    {
        $columnLabels = $form !== null
            ? $this->gridColumns->labelsForField($form, 'line_items', ['Description', 'Qty', 'Unit price'])
            : null;
        $lines = $this->prMapper->linesFromGridValue($values['line_items'] ?? null, $columnLabels);
        if ($lines === []) {
            throw ValidationException::withMessages([
                'values.line_items' => [__('At least one line item is required.')],
            ]);
        }

        $title = trim((string) ($values['requisition_title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages([
                'values.requisition_title' => [__('Requisition title is required.')],
            ]);
        }

        $links = $this->resolveOptionalForeignKeys([
            'project_id' => [__('Project'), 'projects'],
            'rollout_id' => [__('Rollout program'), 'rollout_programs'],
            'site_id' => [__('Site'), 'sites'],
            'boq_line_id' => [__('BOQ line'), null],
        ], $values);

        return [
            'title' => $title,
            'department' => $this->nullableString($values['department'] ?? null),
            'urgency' => $this->nullableString($values['urgency'] ?? null),
            'justification' => $this->nullableString($values['justification'] ?? null),
            'currency' => $this->nullableString($values['currency'] ?? $values['currency_code'] ?? 'PHP') ?? 'PHP',
            'project_id' => $links['project_id'],
            'rollout_id' => $links['rollout_id'],
            'site_id' => $links['site_id'],
            'boq_line_id' => $links['boq_line_id'],
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $prIds
     * @return array<string, mixed>
     */
    public function projectPurchaseOrder(array $values, array $prIds, ?EApprovalForm $form = null): array
    {
        $columnLabels = $form !== null
            ? $this->gridColumns->labelsForField($form, 'line_items', ['Item', 'Description', 'UOM', 'Qty', 'Unit price', 'Discount', 'Amount'])
            : null;
        $lines = $this->poMapper->linesFromGridValue($values['line_items'] ?? null, $columnLabels);

        return [
            'pr_ids' => $prIds,
            'vendor_code' => $this->nullableString($values['vendor'] ?? null),
            'vendor_name' => $this->nullableString($values['supplier'] ?? null),
            'supplier' => $this->nullableString($values['supplier'] ?? null),
            'ship_to' => $this->nullableString($values['ship_to'] ?? null),
            'delivery_date' => $this->nullableString($values['delivery_date'] ?? $values['required_delivery_date'] ?? null),
            'payment_terms' => $this->nullableString($values['payment_terms'] ?? null),
            'currency_code' => $this->nullableString($values['currency_code'] ?? 'PHP') ?? 'PHP',
            'exchange_rate' => $this->numericValue($values['exchange_rate'] ?? 1),
            'delivery_location' => $this->nullableString($values['delivery_location'] ?? null),
            'vat_exempt_amount' => $this->numericValue($values['vat_exempt_amount'] ?? 0),
            'zero_rated_amount' => $this->numericValue($values['zero_rated_amount'] ?? 0),
            'vat_rate' => $this->numericValue($values['vat_rate'] ?? 12),
            'less_discount' => $this->numericValue($values['less_discount'] ?? 0),
            'lines' => $lines === [] ? null : $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function projectApInvoice(array $values, ProcurementPo $po, ?EApprovalForm $form = null): array
    {
        $columnLabels = $form !== null
            ? $this->gridColumns->labelsForField($form, 'line_items', ['Description', 'UOM', 'Qty', 'Unit price', 'Amount'])
            : null;
        $lines = $this->apMapper->linesFromGridValue($values['line_items'] ?? null, $po, $columnLabels);

        return [
            'vendor_invoice_no' => $this->nullableString($values['vendor_invoice_no'] ?? null),
            'invoice_date' => $this->nullableString($values['invoice_date'] ?? null),
            'due_date' => $this->nullableString($values['due_date'] ?? null),
            'payment_terms' => $this->nullableString($values['payment_terms'] ?? null),
            'vat_exempt_amount' => $this->numericValue($values['vat_exempt_amount'] ?? 0),
            'zero_rated_amount' => $this->numericValue($values['zero_rated_amount'] ?? 0),
            'vat_rate' => $this->numericValue($values['vat_rate'] ?? $po->vat_rate ?? 12),
            'less_discount' => $this->numericValue($values['less_discount'] ?? 0),
            'lines' => $lines === [] ? null : $lines,
        ];
    }

    /**
     * @return list<string>
     */
    public function resolvePrIdsFromParentSubmission(?string $parentSubmissionId): array
    {
        if ($parentSubmissionId === null || trim($parentSubmissionId) === '') {
            return [];
        }

        $pr = ProcurementPr::query()
            ->where('e_approval_submission_id', $parentSubmissionId)
            ->first();

        if ($pr instanceof ProcurementPr) {
            return [(string) $pr->id];
        }

        $submission = EApprovalSubmission::query()->with('form')->find($parentSubmissionId);
        if ($submission === null) {
            return [];
        }

        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        if (($metadata['form_family'] ?? null) !== 'purchase_requisition') {
            return [];
        }

        $pr = ProcurementPr::query()
            ->where('e_approval_submission_id', $submission->id)
            ->first();

        return $pr instanceof ProcurementPr ? [(string) $pr->id] : [];
    }

    /**
     * @param  array<string, array{0: string, 1: string|null}>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, string|null>
     */
    private function resolveOptionalForeignKeys(array $fields, array $values): array
    {
        $resolved = [];
        $errors = [];

        foreach ($fields as $key => [$label, $table]) {
            try {
                $resolved[$key] = $this->foreignKeys->resolve($values[$key] ?? null, $key, $label, $table);
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $exception->errors());
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $resolved;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function numericValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }
}
