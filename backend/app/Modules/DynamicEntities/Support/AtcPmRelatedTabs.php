<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

final class AtcPmRelatedTabs
{
    /**
     * Related entity tabs shown on Tower Sites detail (matches Metacoresoft ATC UI).
     *
     * @return list<array{entity_slug: string, foreign_field: string, label: string}>
     */
    public static function forTowerSites(): array
    {
        return [
            ['entity_slug' => 'construction_projects', 'foreign_field' => 'tower_site_id', 'label' => 'Construction Projects'],
            ['entity_slug' => 'land_leases', 'foreign_field' => 'tower_site_id', 'label' => 'Land Leases'],
            ['entity_slug' => 'power_trackers', 'foreign_field' => 'tower_site_id', 'label' => 'Power Trackers'],
            ['entity_slug' => 'procurement_requests', 'foreign_field' => 'tower_site_id', 'label' => 'Procurement Requests'],
            ['entity_slug' => 'saq_trackers', 'foreign_field' => 'tower_site_id', 'label' => 'SAQ Trackers'],
            ['entity_slug' => 'telco_contracts', 'foreign_field' => 'tower_site_id', 'label' => 'Telco Contracts'],
            ['entity_slug' => 'site_permits', 'foreign_field' => 'tower_site_id', 'label' => 'Site Permits'],
            ['entity_slug' => 'site_colocations', 'foreign_field' => 'tower_site_id', 'label' => 'Site Colocations'],
            ['entity_slug' => 'postcon_trackers', 'foreign_field' => 'tower_site_id', 'label' => 'Postcon Trackers'],
            ['entity_slug' => 'fixed_assets', 'foreign_field' => 'tower_site_id', 'label' => 'Fixed Assets'],
            ['entity_slug' => 'construction_activities', 'foreign_field' => 'tower_site_id', 'label' => 'Construction Activities'],
            AtcTicketingPack::towerSitesTab(),
        ];
    }

    /**
     * Ordered PM + reference entity slugs for Phase 1 data import.
     *
     * @return list<string>
     */
    public static function phase1EntitySlugs(): array
    {
        return [
            'territories',
            'project_teams',
            'milestone_catalogue',
            'branches',
            'electric_utilities',
            'vendors',
            'lessors',
            'tower_sites',
            'construction_projects',
            'construction_activities',
            'saq_trackers',
            'power_trackers',
            'postcon_trackers',
            'land_leases',
            'site_permits',
            'telco_contracts',
            'site_colocations',
            'site_documents',
        ];
    }
}
