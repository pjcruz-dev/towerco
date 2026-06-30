<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use Illuminate\Validation\ValidationException;

final class ProcurementNumberingSeriesCatalog
{
    /**
     * @return array<string, array{prefix: string, padding: int, reset_rule: string, next_sequence: int}>
     */
    public function resolveAll(): array
    {
        $settings = app(ProcurementOneSettingsService::class);
        $raw = $settings->getJson(ProcurementOneSettingsService::NUMBERING_SERIES);
        $resolved = [];

        foreach (ProcurementDocumentType::all() as $documentType) {
            $resolved[$documentType] = $this->resolveForType(
                $documentType,
                is_array($raw[$documentType] ?? null) ? $raw[$documentType] : null,
            );
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>|null  $override
     * @return array{prefix: string, padding: int, reset_rule: string, next_sequence: int}
     */
    public function resolveForType(string $documentType, ?array $override = null): array
    {
        $defaults = $this->defaultsForType($documentType);
        if ($override === null) {
            return $defaults;
        }

        return $this->normalizeSeries($documentType, $override, $defaults['next_sequence']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{prefix: string, padding: int, reset_rule: string, next_sequence: int}>
     */
    public function validateAndNormalizePayload(array $payload): array
    {
        $existing = $this->resolveAll();
        $normalized = [];

        foreach (ProcurementDocumentType::all() as $documentType) {
            if (! array_key_exists($documentType, $payload)) {
                continue;
            }

            if (! is_array($payload[$documentType])) {
                throw ValidationException::withMessages([
                    "numbering_series.{$documentType}" => [__('Numbering series must be an object.')],
                ]);
            }

            $normalized[$documentType] = $this->normalizeSeries(
                $documentType,
                $payload[$documentType],
                (int) ($existing[$documentType]['next_sequence'] ?? 1),
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $series
     * @return array{prefix: string, padding: int, reset_rule: string, next_sequence: int}
     */
    private function normalizeSeries(string $documentType, array $series, int $fallbackSequence): array
    {
        $defaults = $this->defaultsForType($documentType);
        $prefix = trim((string) ($series['prefix'] ?? $defaults['prefix']));
        if ($prefix === '') {
            throw ValidationException::withMessages([
                "numbering_series.{$documentType}.prefix" => [__('Prefix is required.')],
            ]);
        }

        $padding = (int) ($series['padding'] ?? $defaults['padding']);
        if ($padding < 1 || $padding > 10) {
            throw ValidationException::withMessages([
                "numbering_series.{$documentType}.padding" => [__('Padding must be between 1 and 10.')],
            ]);
        }

        $resetRule = strtolower(trim((string) ($series['reset_rule'] ?? $defaults['reset_rule'])));
        if (! in_array($resetRule, ['never', 'yearly', 'monthly'], true)) {
            throw ValidationException::withMessages([
                "numbering_series.{$documentType}.reset_rule" => [__('Reset rule must be never, yearly, or monthly.')],
            ]);
        }

        $nextSequence = (int) ($series['next_sequence'] ?? $fallbackSequence);
        if ($nextSequence < 1) {
            $nextSequence = 1;
        }

        return [
            'prefix' => $prefix,
            'padding' => $padding,
            'reset_rule' => $resetRule,
            'next_sequence' => $nextSequence,
        ];
    }

    /**
     * @return array{prefix: string, padding: int, reset_rule: string, next_sequence: int}
     */
    private function defaultsForType(string $documentType): array
    {
        $yearToken = '{YEAR}';

        return match ($documentType) {
            ProcurementDocumentType::PURCHASE_REQUISITION => [
                'prefix' => "PR-{$yearToken}-",
                'padding' => 5,
                'reset_rule' => 'yearly',
                'next_sequence' => 1,
            ],
            ProcurementDocumentType::PURCHASE_ORDER => [
                'prefix' => "PO-{$yearToken}-",
                'padding' => 5,
                'reset_rule' => 'yearly',
                'next_sequence' => 1,
            ],
            ProcurementDocumentType::GOODS_RECEIPT => [
                'prefix' => "GRN-{$yearToken}-",
                'padding' => 5,
                'reset_rule' => 'yearly',
                'next_sequence' => 1,
            ],
            ProcurementDocumentType::AP_INVOICE => [
                'prefix' => "APINV-{$yearToken}-",
                'padding' => 5,
                'reset_rule' => 'yearly',
                'next_sequence' => 1,
            ],
            ProcurementDocumentType::CREDIT_NOTE => [
                'prefix' => "CN-{$yearToken}-",
                'padding' => 5,
                'reset_rule' => 'yearly',
                'next_sequence' => 1,
            ],
            ProcurementDocumentType::PAYMENT_REQUEST => [
                'prefix' => "PAY-{$yearToken}-",
                'padding' => 5,
                'reset_rule' => 'yearly',
                'next_sequence' => 1,
            ],
            ProcurementDocumentType::PAYMENT_BATCH => [
                'prefix' => "PAYB-{$yearToken}-",
                'padding' => 5,
                'reset_rule' => 'yearly',
                'next_sequence' => 1,
            ],
            ProcurementDocumentType::REQUEST_FOR_QUOTATION => [
                'prefix' => "RFQ-{$yearToken}-",
                'padding' => 5,
                'reset_rule' => 'yearly',
                'next_sequence' => 1,
            ],
            ProcurementDocumentType::VENDOR_CONTRACT => [
                'prefix' => "VCON-{$yearToken}-",
                'padding' => 5,
                'reset_rule' => 'yearly',
                'next_sequence' => 1,
            ],
            default => [
                'prefix' => strtoupper(substr($documentType, 0, 3))."-{$yearToken}-",
                'padding' => 5,
                'reset_rule' => 'yearly',
                'next_sequence' => 1,
            ],
        };
    }
}
