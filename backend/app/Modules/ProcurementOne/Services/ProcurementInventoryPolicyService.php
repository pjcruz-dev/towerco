<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

final class ProcurementInventoryPolicyService
{
    public const SETTINGS_KEY = 'inventory_policy';

    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
    ) {}

    /**
     * @return array{
     *   inventory_mode: string,
     *   default_receipt_location_id: string|null,
     *   auto_create_assets_on_deploy: bool
     * }
     */
    public function policy(): array
    {
        $raw = $this->settings->getJson(self::SETTINGS_KEY);
        $mode = (string) ($raw['inventory_mode'] ?? 'none');
        $defaultLocation = $raw['default_receipt_location_id'] ?? null;

        return [
            'inventory_mode' => in_array($mode, ['none', 'simple'], true) ? $mode : 'none',
            'default_receipt_location_id' => is_string($defaultLocation) && $defaultLocation !== ''
                ? $defaultLocation
                : null,
            'auto_create_assets_on_deploy' => (bool) ($raw['auto_create_assets_on_deploy'] ?? false),
        ];
    }

    public function isSimpleModeEnabled(): bool
    {
        return $this->policy()['inventory_mode'] === 'simple';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   inventory_mode: string,
     *   default_receipt_location_id: string|null,
     *   auto_create_assets_on_deploy: bool
     * }
     */
    public function validateAndNormalize(array $input): array
    {
        $mode = (string) ($input['inventory_mode'] ?? 'none');
        $defaultLocation = $input['default_receipt_location_id'] ?? null;

        return [
            'inventory_mode' => in_array($mode, ['none', 'simple'], true) ? $mode : 'none',
            'default_receipt_location_id' => is_string($defaultLocation) && trim($defaultLocation) !== ''
                ? trim($defaultLocation)
                : null,
            'auto_create_assets_on_deploy' => (bool) ($input['auto_create_assets_on_deploy'] ?? false),
        ];
    }
}
