<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalFormPolicySupport;

final class EApprovalApprovalPolicyContextExtractor
{
    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function extract(EApprovalForm $form, array $values, ?string $amountField = null): array
    {
        $family = EApprovalFormPolicySupport::documentFamily($form);
        $field = $amountField ?? $this->defaultAmountField($family);

        return [
            'document_family' => $family,
            'department' => $this->stringValue($values, 'department'),
            'category' => (string) ($form->category ?? ''),
            'urgency' => $this->stringValue($values, 'urgency'),
            'amount' => $this->numericValue($values, $field),
            'amount_field' => $field,
        ];
    }

    private function defaultAmountField(?string $documentFamily): string
    {
        return match ($documentFamily) {
            'purchase_requisition' => 'estimated_total',
            'purchase_order' => 'total_amount',
            default => 'amount',
        };
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function stringValue(array $values, string $key): ?string
    {
        $raw = $values[$key] ?? null;
        if ($raw === null) {
            return null;
        }

        $trimmed = trim((string) $raw);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function numericValue(array $values, string $key): ?float
    {
        $raw = $values[$key] ?? null;
        if ($raw === null || $raw === '') {
            foreach (['grand_total', 'total_amount', 'estimated_total', 'requested_amount'] as $fallback) {
                if ($fallback === $key) {
                    continue;
                }
                $candidate = $values[$fallback] ?? null;
                if ($candidate !== null && $candidate !== '' && is_numeric($candidate)) {
                    return (float) $candidate;
                }
            }

            return null;
        }

        if (! is_numeric($raw)) {
            $normalized = preg_replace('/[^\d.\-]/', '', (string) $raw);

            return is_numeric($normalized) ? (float) $normalized : null;
        }

        return (float) $raw;
    }
}
