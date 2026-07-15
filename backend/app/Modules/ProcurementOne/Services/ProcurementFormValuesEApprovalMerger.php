<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Support\ProcurementComposeMetadata;

final class ProcurementFormValuesEApprovalMerger
{
    /**
     * Merge compose-only form fields (approvers, etc.) onto PR-backed E-Approval values.

     *

     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $compose
     * @return array<string, mixed>
     */
    public function mergePurchaseRequisition(array $base, array $compose): array
    {

        return $this->merge($base, $compose, ProcurementComposeMetadata::purchaseRequisitionBaseFieldKeys());

    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $compose
     * @return array<string, mixed>
     */
    public function mergePurchaseOrder(array $base, array $compose): array
    {

        return $this->merge($base, $compose, ProcurementComposeMetadata::purchaseOrderBaseFieldKeys());

    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $compose
     * @return array<string, mixed>
     */
    public function mergeApInvoice(array $base, array $compose): array
    {

        return $this->merge($base, $compose, ProcurementComposeMetadata::apInvoiceBaseFieldKeys());

    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $compose
     * @param  list<string>  $reservedKeys
     * @return array<string, mixed>
     */
    private function merge(array $base, array $compose, array $reservedKeys): array
    {

        $merged = $base;

        $reserved = array_flip($reservedKeys);

        foreach ($compose as $key => $value) {

            $name = trim((string) $key);

            if ($name === '' || isset($reserved[$name])) {

                continue;

            }

            if ($value === null) {

                continue;

            }

            if (is_scalar($value) || is_array($value)) {

                $string = is_scalar($value) ? trim((string) $value) : $value;

                if ($string === '' || $string === []) {

                    continue;

                }

                $merged[$name] = $string;

                continue;

            }

            if (is_bool($value)) {

                $merged[$name] = $value ? 'true' : 'false';

            }

        }

        return $merged;

    }
}
