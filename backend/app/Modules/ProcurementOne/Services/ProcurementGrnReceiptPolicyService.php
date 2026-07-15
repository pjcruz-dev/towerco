<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use Illuminate\Validation\ValidationException;

final class ProcurementGrnReceiptPolicyService
{
    public const SETTINGS_KEY = 'gr_receipt_policy';

    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
    ) {}

    /**
     * @return array{tolerance_percent: float, mode: string}
     */
    public function policy(): array
    {
        $raw = $this->settings->getJson(self::SETTINGS_KEY);
        $tolerance = (float) ($raw['tolerance_percent'] ?? 5);
        $mode = (string) ($raw['mode'] ?? 'block');

        return [
            'tolerance_percent' => max(0, min(100, $tolerance)),
            'mode' => in_array($mode, ['warn', 'block'], true) ? $mode : 'block',
        ];
    }

    /**
     * @param  array{tolerance_percent?: float|int, mode?: string}  $input
     * @return array{tolerance_percent: float, mode: string}
     */
    public function validateAndNormalize(array $input): array
    {
        $tolerance = (float) ($input['tolerance_percent'] ?? 5);
        $mode = (string) ($input['mode'] ?? 'block');

        return [
            'tolerance_percent' => max(0, min(100, $tolerance)),
            'mode' => in_array($mode, ['warn', 'block'], true) ? $mode : 'block',
        ];
    }

    /**
     * @return array{blocked: bool, warning: string|null}
     */
    public function evaluateLineReceipt(ProcurementPoLine $poLine, float $quantityReceived, ?string $excludeGrnId = null): array
    {
        $remaining = app(ProcurementGrnPoBalanceService::class)->remainingQuantityForPoLine($poLine, $excludeGrnId);
        if ($quantityReceived <= 0) {
            return ['blocked' => false, 'warning' => null];
        }

        if ($quantityReceived <= $remaining + 0.0001) {
            return ['blocked' => false, 'warning' => null];
        }

        $policy = $this->policy();
        $allowedMax = $remaining + ($remaining * ($policy['tolerance_percent'] / 100));
        if ($quantityReceived <= $allowedMax + 0.0001) {
            return [
                'blocked' => false,
                'warning' => __('Received quantity exceeds open PO balance by within :pct% tolerance.', [
                    'pct' => number_format($policy['tolerance_percent'], 2),
                ]),
            ];
        }

        $message = __('Received quantity :received exceeds allowed maximum :max for line :description.', [
            'received' => number_format($quantityReceived, 4),
            'max' => number_format($allowedMax, 4),
            'description' => $poLine->description,
        ]);

        if ($policy['mode'] === 'block') {
            throw ValidationException::withMessages([
                'lines' => [$message],
            ]);
        }

        return ['blocked' => false, 'warning' => $message];
    }
}
