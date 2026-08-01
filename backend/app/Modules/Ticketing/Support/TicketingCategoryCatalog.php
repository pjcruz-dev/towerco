<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Support;

use App\Modules\Ticketing\Services\TicketingSettingsService;

final class TicketingCategoryCatalog
{
    /**
     * @return list<string>
     */
    public static function defaults(): array
    {
        return [
            'general',
            'bug',
            'feature_request',
            'access',
            'billing',
            'integration',
            'operations',
        ];
    }

    /**
     * @return list<string>
     */
    public function resolve(): array
    {
        return array_values(array_map(
            static fn (array $option): string => $option['id'],
            $this->resolveOptions(),
        ));
    }

    /**
     * @return list<array{id: string, label: string, sla_response_minutes: ?int, sla_escalation_minutes: ?int}>
     */
    public function resolveOptions(): array
    {
        $settings = app(TicketingSettingsService::class);
        $raw = $settings->getString(TicketingSettingsService::CATEGORIES);
        if ($raw === null || trim($raw) === '') {
            return self::defaultOptions();
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return self::defaultOptions();
        }

        $options = [];
        $seen = [];
        foreach ($decoded as $item) {
            $normalized = self::normalizeItem($item);
            if ($normalized === null || isset($seen[$normalized['id']])) {
                continue;
            }
            $seen[$normalized['id']] = true;
            $options[] = $normalized;
        }

        return $options !== [] ? $options : self::defaultOptions();
    }

    /**
     * @return array{id: string, label: string, sla_response_minutes: ?int, sla_escalation_minutes: ?int}|null
     */
    public function optionFor(?string $category): ?array
    {
        if ($category === null || trim($category) === '') {
            return null;
        }

        $slug = strtolower(trim($category));
        foreach ($this->resolveOptions() as $option) {
            if ($option['id'] === $slug) {
                return $option;
            }
        }

        return null;
    }

    public function isValid(?string $category): bool
    {
        if ($category === null || $category === '') {
            return true;
        }

        return in_array(strtolower($category), $this->resolve(), true);
    }

    /**
     * Human-readable label for a category slug (defaults / known packs / custom fallback).
     */
    public static function labelFor(string $slug): string
    {
        $key = strtolower(trim($slug));

        return self::knownLabels()[$key] ?? self::humanizeSlug($key);
    }

    /**
     * @return list<array{id: string, label: string, sla_response_minutes: ?int, sla_escalation_minutes: ?int}>
     */
    public static function defaultOptions(): array
    {
        return array_map(
            static fn (string $slug): array => [
                'id' => $slug,
                'label' => self::labelFor($slug),
                'sla_response_minutes' => null,
                'sla_escalation_minutes' => null,
            ],
            self::defaults(),
        );
    }

    /**
     * @param  mixed  $item
     * @return array{id: string, label: string, sla_response_minutes: ?int, sla_escalation_minutes: ?int}|null
     */
    public static function normalizeItem(mixed $item): ?array
    {
        if (is_string($item)) {
            $slug = strtolower(trim($item));
            if ($slug === '' || ! preg_match('/^[a-z0-9_]+$/', $slug)) {
                return null;
            }

            return [
                'id' => $slug,
                'label' => self::labelFor($slug),
                'sla_response_minutes' => null,
                'sla_escalation_minutes' => null,
            ];
        }

        if (! is_array($item) || ! isset($item['id'])) {
            return null;
        }

        $slug = strtolower(trim((string) $item['id']));
        if ($slug === '' || ! preg_match('/^[a-z0-9_]+$/', $slug)) {
            return null;
        }

        $label = isset($item['label']) ? trim((string) $item['label']) : '';
        if ($label === '') {
            $label = self::labelFor($slug);
        }

        return [
            'id' => $slug,
            'label' => mb_substr($label, 0, 120),
            'sla_response_minutes' => self::normalizeOptionalMinutes($item['sla_response_minutes'] ?? null),
            'sla_escalation_minutes' => self::normalizeOptionalMinutes($item['sla_escalation_minutes'] ?? null),
        ];
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array{id: string, label: string, sla_response_minutes: ?int, sla_escalation_minutes: ?int}>
     */
    public static function normalizeList(array $items): array
    {
        $options = [];
        $seen = [];
        foreach ($items as $item) {
            $normalized = self::normalizeItem($item);
            if ($normalized === null || isset($seen[$normalized['id']])) {
                continue;
            }
            $seen[$normalized['id']] = true;
            $options[] = $normalized;
        }

        return $options;
    }

    private static function normalizeOptionalMinutes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $minutes = (int) $value;

        return $minutes >= 1 ? $minutes : null;
    }

    public static function humanizeSlug(string $slug): string
    {
        $trimmed = trim($slug);
        if ($trimmed === '') {
            return '';
        }

        return ucfirst(str_replace('_', ' ', $trimmed));
    }

    /**
     * @return array<string, string>
     */
    public static function knownLabels(): array
    {
        return [
            'general' => 'General',
            'bug' => 'Bug',
            'feature_request' => 'Feature request',
            'access' => 'Access',
            'billing' => 'Billing',
            'integration' => 'Integration',
            'operations' => 'Operations',
            // Enterprise IT
            'hw_workstations' => 'Hardware · Workstations',
            'hw_peripherals' => 'Hardware · Peripherals',
            'hw_mobile_telecom' => 'Hardware · Mobile & telecom',
            'hw_infrastructure' => 'Hardware · Infrastructure',
            'hw_network_devices' => 'Hardware · Network devices',
            'sw_productivity' => 'Software · Core productivity',
            'sw_erp_crm' => 'Software · ERP / CRM',
            'sw_engineering_tools' => 'Software · Engineering tools',
            'sw_operating_systems' => 'Software · Operating systems',
            'sw_local_apps' => 'Software · Local applications',
            'id_account_management' => 'Identity · Account management',
            'id_access_requests' => 'Identity · Access requests',
            'id_network_access' => 'Identity · Network access',
            'id_security_incident' => 'Identity · Security incident',
            'wp_conference_rooms' => 'Workplace · Conference rooms',
            'wp_office_printing' => 'Workplace · Office printing',
            // Procurement-One
            'procurement_delivery_delay' => 'Procurement · Delivery delay',
            'procurement_vendor_issue' => 'Procurement · Vendor issue',
            'procurement_invoice_dispute' => 'Procurement · Invoice dispute',
            'procurement_grn_mismatch' => 'Procurement · GRN mismatch',
            'procurement_approval_delay' => 'Procurement · Approval delay',
            'procurement_contract' => 'Procurement · Contract',
            'procurement_general' => 'Procurement · General',
        ];
    }
}
