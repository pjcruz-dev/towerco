<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Support\ProcurementApMatchMode;

final class ProcurementApInvoiceMatchPolicyService
{
    public const SETTINGS_KEY = 'ap_invoice_match_policy';

    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
    ) {}

    /**
     * @return array{match_mode: string, tolerance_percent: float, mode: string, require_grn_posted: bool}
     */
    public function policy(): array
    {
        $raw = $this->settings->getJson(self::SETTINGS_KEY);
        $matchMode = (string) ($raw['match_mode'] ?? ProcurementApMatchMode::THREE_WAY);
        $mode = (string) ($raw['mode'] ?? 'block');

        return [
            'match_mode' => ProcurementApMatchMode::isValid($matchMode) ? $matchMode : ProcurementApMatchMode::THREE_WAY,
            'tolerance_percent' => max(0, min(100, (float) ($raw['tolerance_percent'] ?? 2))),
            'mode' => in_array($mode, ['warn', 'block'], true) ? $mode : 'block',
            'require_grn_posted' => (bool) ($raw['require_grn_posted'] ?? true),
        ];
    }

    public function blocksMismatch(): bool
    {
        return $this->policy()['mode'] === 'block';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{match_mode: string, tolerance_percent: float, mode: string, require_grn_posted: bool}
     */
    public function validateAndNormalize(array $input): array
    {
        $matchMode = (string) ($input['match_mode'] ?? ProcurementApMatchMode::THREE_WAY);
        $mode = (string) ($input['mode'] ?? 'block');

        return [
            'match_mode' => ProcurementApMatchMode::isValid($matchMode) ? $matchMode : ProcurementApMatchMode::THREE_WAY,
            'tolerance_percent' => max(0, min(100, (float) ($input['tolerance_percent'] ?? 2))),
            'mode' => in_array($mode, ['warn', 'block'], true) ? $mode : 'block',
            'require_grn_posted' => (bool) ($input['require_grn_posted'] ?? true),
        ];
    }
}
