<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Support;

final class TicketingCategoryPackCatalog
{
    public const PACK_PROCUREMENT_ONE = 'procurement_one';

    public const PACK_ENTERPRISE_IT = 'enterprise_it';

    /**
     * @return list<string>
     */
    public static function procurementOneCategories(): array
    {
        return [
            'procurement_delivery_delay',
            'procurement_vendor_issue',
            'procurement_invoice_dispute',
            'procurement_grn_mismatch',
            'procurement_approval_delay',
            'procurement_contract',
            'procurement_general',
        ];
    }

    /**
     * Enterprise IT service-desk categories (flat slugs for ticket.category).
     *
     * @return list<string>
     */
    public static function enterpriseItCategories(): array
    {
        return [
            // Hardware (asset-based)
            'hw_workstations',
            'hw_peripherals',
            'hw_mobile_telecom',
            'hw_infrastructure',
            'hw_network_devices',
            // Software & enterprise applications
            'sw_productivity',
            'sw_erp_crm',
            'sw_engineering_tools',
            'sw_operating_systems',
            'sw_local_apps',
            // Identity, access & security
            'id_account_management',
            'id_access_requests',
            'id_network_access',
            'id_security_incident',
            // Workplace & facilities IT
            'wp_conference_rooms',
            'wp_office_printing',
        ];
    }

    /**
     * @return list<array{id: string, label: string, description: string, categories: list<string>}>
     */
    public function all(): array
    {
        return [
            [
                'id' => self::PACK_ENTERPRISE_IT,
                'label' => 'Enterprise IT',
                'description' => 'Hardware, software, identity/security, and workplace IT categories for corporate service desk triage.',
                'categories' => self::enterpriseItCategories(),
            ],
            [
                'id' => self::PACK_PROCUREMENT_ONE,
                'label' => 'Procurement-One',
                'description' => 'Delivery delays, vendor issues, GRN mismatches, invoice disputes, and contract follow-ups.',
                'categories' => self::procurementOneCategories(),
            ],
        ];
    }

    public function isValid(string $packId): bool
    {
        return in_array($packId, array_column($this->all(), 'id'), true);
    }

    /**
     * @return list<string>
     */
    public function categoriesFor(string $packId): array
    {
        return match ($packId) {
            self::PACK_ENTERPRISE_IT => self::enterpriseItCategories(),
            self::PACK_PROCUREMENT_ONE => self::procurementOneCategories(),
            default => [],
        };
    }
}
