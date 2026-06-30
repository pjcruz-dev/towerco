<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use Illuminate\Validation\ValidationException;

final class ProcurementRfqScoringPolicyService
{
    public const SETTINGS_KEY = 'rfq_scoring_policy';

    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
    ) {}

    /**
     * @return array{
     *   weight_price: float,
     *   weight_lead_time: float,
     *   weight_accreditation: float,
     *   weight_line_coverage: float,
     *   vendor_portal_enabled: bool,
     *   notify_buyer_on_bid: bool,
     *   notify_buyer_email: bool,
     *   auto_close_at_deadline: bool,
     *   vendor_inbox_enabled: bool
     * }
     */
    public function policy(): array
    {
        $raw = $this->settings->getJson(self::SETTINGS_KEY);

        return $this->normalize(is_array($raw) ? $raw : []);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   weight_price: float,
     *   weight_lead_time: float,
     *   weight_accreditation: float,
     *   weight_line_coverage: float,
     *   vendor_portal_enabled: bool,
     *   notify_buyer_on_bid: bool,
     *   notify_buyer_email: bool,
     *   auto_close_at_deadline: bool,
     *   vendor_inbox_enabled: bool
     * }
     */
    public function validateAndNormalize(array $input): array
    {
        return $this->normalize($input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   weight_price: float,
     *   weight_lead_time: float,
     *   weight_accreditation: float,
     *   weight_line_coverage: float,
     *   vendor_portal_enabled: bool,
     *   notify_buyer_on_bid: bool,
     *   notify_buyer_email: bool,
     *   auto_close_at_deadline: bool,
     *   vendor_inbox_enabled: bool
     * }
     */
    private function normalize(array $input): array
    {
        $weights = [
            'weight_price' => max(0, (float) ($input['weight_price'] ?? 50)),
            'weight_lead_time' => max(0, (float) ($input['weight_lead_time'] ?? 25)),
            'weight_accreditation' => max(0, (float) ($input['weight_accreditation'] ?? 15)),
            'weight_line_coverage' => max(0, (float) ($input['weight_line_coverage'] ?? 10)),
        ];

        $total = array_sum($weights);
        if ($total <= 0) {
            throw ValidationException::withMessages([
                'rfq_scoring_policy' => [__('At least one scoring weight must be greater than zero.')],
            ]);
        }

        foreach ($weights as $key => $value) {
            $weights[$key] = round(($value / $total) * 100, 2);
        }

        return $weights + [
            'vendor_portal_enabled' => (bool) ($input['vendor_portal_enabled'] ?? true),
            'notify_buyer_on_bid' => (bool) ($input['notify_buyer_on_bid'] ?? true),
            'notify_buyer_email' => (bool) ($input['notify_buyer_email'] ?? true),
            'auto_close_at_deadline' => (bool) ($input['auto_close_at_deadline'] ?? true),
            'vendor_inbox_enabled' => (bool) ($input['vendor_inbox_enabled'] ?? true),
        ];
    }
}
