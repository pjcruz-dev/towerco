<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

final class ProcurementVendorAccreditationPolicyService
{
    public const SETTINGS_KEY = 'vendor_accreditation_policy';

    public function __construct(
        private readonly ProcurementOneSettingsService $settings,
    ) {}

    /**
     * @return array{enabled: bool, mode: string}
     */
    public function policy(): array
    {
        $raw = $this->settings->getJson(self::SETTINGS_KEY);
        $enabled = (bool) ($raw['enabled'] ?? false);
        $mode = (string) ($raw['mode'] ?? 'warn');
        if (! in_array($mode, ['warn', 'block'], true)) {
            $mode = 'warn';
        }

        return [
            'enabled' => $enabled,
            'mode' => $mode,
        ];
    }

    public function isEnforced(): bool
    {
        return $this->policy()['enabled'] === true;
    }

    public function blocksNonAccredited(): bool
    {
        $policy = $this->policy();

        return $policy['enabled'] && $policy['mode'] === 'block';
    }

    /**
     * @param  array{enabled?: bool, mode?: string}  $input
     */
    public function validateAndNormalize(array $input): array
    {
        $enabled = (bool) ($input['enabled'] ?? false);
        $mode = (string) ($input['mode'] ?? 'warn');
        if (! in_array($mode, ['warn', 'block'], true)) {
            $mode = 'warn';
        }

        return [
            'enabled' => $enabled,
            'mode' => $mode,
        ];
    }
}
