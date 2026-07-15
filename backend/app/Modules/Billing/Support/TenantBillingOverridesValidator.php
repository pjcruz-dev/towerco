<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use Illuminate\Validation\ValidationException;

final class TenantBillingOverridesValidator
{
    /**
     * @return array<string, mixed>|null
     */
    public static function validate(mixed $input): ?array
    {
        if ($input === null) {
            return null;
        }

        if (! is_array($input)) {
            throw ValidationException::withMessages([
                'billing_overrides' => [__('Billing overrides must be an object.')],
            ]);
        }

        if ($input === []) {
            return null;
        }

        $normalized = [];

        if (array_key_exists('seat_limit', $input)) {
            $seat = (int) $input['seat_limit'];
            if ($seat < 1 || $seat > 10000) {
                throw ValidationException::withMessages([
                    'billing_overrides.seat_limit' => [__('Seat limit override must be between 1 and 10000.')],
                ]);
            }
            $normalized['seat_limit'] = $seat;
        }

        if (array_key_exists('included_paid_seats', $input)) {
            $seats = (int) $input['included_paid_seats'];
            if ($seats < 1 || $seats > 10000) {
                throw ValidationException::withMessages([
                    'billing_overrides.included_paid_seats' => [__('Included paid seats must be between 1 and 10000.')],
                ]);
            }
            $normalized['included_paid_seats'] = $seats;
        }

        if (array_key_exists('included_rfi_units', $input)) {
            $units = (int) $input['included_rfi_units'];
            if ($units < 0 || $units > 100000) {
                throw ValidationException::withMessages([
                    'billing_overrides.included_rfi_units' => [__('Included RFI units must be between 0 and 100000.')],
                ]);
            }
            $normalized['included_rfi_units'] = $units;
        }

        if (array_key_exists('grandfather_rfi_units', $input)) {
            $units = (int) $input['grandfather_rfi_units'];
            if ($units < 0 || $units > 100000) {
                throw ValidationException::withMessages([
                    'billing_overrides.grandfather_rfi_units' => [__('Grandfather RFI units must be between 0 and 100000.')],
                ]);
            }
            $normalized['grandfather_rfi_units'] = $units;
        }

        if (array_key_exists('annual_discount_percent', $input)) {
            if ($input['annual_discount_percent'] === null || $input['annual_discount_percent'] === '') {
                $normalized['annual_discount_percent'] = null;
            } else {
                $percent = (float) $input['annual_discount_percent'];
                if ($percent < 0 || $percent > 80) {
                    throw ValidationException::withMessages([
                        'billing_overrides.annual_discount_percent' => [__('Annual discount percent must be between 0 and 80.')],
                    ]);
                }
                $normalized['annual_discount_percent'] = round($percent, 2);
            }
        }

        if (array_key_exists('modules', $input) && is_array($input['modules'])) {
            $modules = [];
            foreach ($input['modules'] as $moduleKey => $moduleConfig) {
                if (! is_string($moduleKey) || ! is_array($moduleConfig)) {
                    continue;
                }
                $modules[$moduleKey] = self::normalizeModule($moduleKey, $moduleConfig);
            }
            if ($modules !== []) {
                $normalized['modules'] = $modules;
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function normalizeModule(string $moduleKey, array $config): array
    {
        $out = [];

        if ($moduleKey === 'e_approval') {
            if (array_key_exists('file_uploads', $config)) {
                $out['file_uploads'] = (bool) $config['file_uploads'];
            }
            if (array_key_exists('max_file_fields', $config)) {
                $max = $config['max_file_fields'];
                $out['max_file_fields'] = $max === null || $max === '' ? null : max(0, (int) $max);
            }
        }

        if ($moduleKey === 'project_one' && array_key_exists('rollout_file_uploads', $config)) {
            $out['rollout_file_uploads'] = (bool) $config['rollout_file_uploads'];
        }

        if ($moduleKey === 'ticketing') {
            if (array_key_exists('enabled', $config)) {
                $out['enabled'] = (bool) $config['enabled'];
            }
            if (array_key_exists('file_uploads', $config)) {
                $out['file_uploads'] = (bool) $config['file_uploads'];
            }
            if (array_key_exists('max_attachments_per_ticket', $config)) {
                $max = $config['max_attachments_per_ticket'];
                $out['max_attachments_per_ticket'] = $max === null || $max === '' ? null : max(0, (int) $max);
            }
        }

        if ($moduleKey === 'procurement_one') {
            if (array_key_exists('enabled', $config)) {
                $out['enabled'] = (bool) $config['enabled'];
            }
            if (array_key_exists('goods_receipt', $config)) {
                $out['goods_receipt'] = (bool) $config['goods_receipt'];
            }
            if (array_key_exists('advanced_numbering', $config)) {
                $out['advanced_numbering'] = (bool) $config['advanced_numbering'];
            }
            if (array_key_exists('inventory', $config)) {
                $out['inventory'] = (bool) $config['inventory'];
            }
            if (array_key_exists('ap_invoices', $config)) {
                $out['ap_invoices'] = (bool) $config['ap_invoices'];
            }
            if (array_key_exists('payment_tracking', $config)) {
                $out['payment_tracking'] = (bool) $config['payment_tracking'];
            }
            if (array_key_exists('rfq_sourcing', $config)) {
                $out['rfq_sourcing'] = (bool) $config['rfq_sourcing'];
            }
            if (array_key_exists('vendor_contracts', $config)) {
                $out['vendor_contracts'] = (bool) $config['vendor_contracts'];
            }
            if (array_key_exists('reporting_exports', $config)) {
                $out['reporting_exports'] = (bool) $config['reporting_exports'];
            }
        }

        return $out;
    }
}
