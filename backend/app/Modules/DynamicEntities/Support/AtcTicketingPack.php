<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * ATC operational ticketing pack (Dynamic Entities).
 *
 * Greenfield — current Metacoresoft dump has no ticket entities/tables.
 * Native TowerOS /ticketing and E-Approval stay separate.
 */
final class AtcTicketingPack
{
    /**
     * Ordered entity slugs for Phase 5 (seed + future dump ETL).
     *
     * @return list<string>
     */
    public static function phase5EntitySlugs(): array
    {
        return [
            'ticket_categories',
            'site_tickets',
            'ticket_activities',
        ];
    }

    /**
     * @return list<array{entity_slug: string, foreign_field: string, label: string}>
     */
    public static function forSiteTickets(): array
    {
        return [
            [
                'entity_slug' => 'ticket_activities',
                'foreign_field' => 'site_ticket_id',
                'label' => 'Activities',
            ],
        ];
    }

    /**
     * Related tab entry for Tower Sites detail.
     *
     * @return array{entity_slug: string, foreign_field: string, label: string}
     */
    public static function towerSitesTab(): array
    {
        return [
            'entity_slug' => 'site_tickets',
            'foreign_field' => 'tower_site_id',
            'label' => 'Site Tickets',
        ];
    }

    /**
     * @return list<array{
     *   slug: string,
     *   name: string,
     *   description: string,
     *   sort_order: int,
     *   fields: list<array<string, mixed>>
     * }>
     */
    public static function entityDefinitions(): array
    {
        return [
            [
                'slug' => 'ticket_categories',
                'name' => 'Ticket Categories',
                'description' => 'ATC operational ticket categories and default SLA hours.',
                'sort_order' => 10,
                'fields' => [
                    ['name' => 'code', 'label' => 'Code', 'type' => DynFieldType::TEXT, 'is_required' => true, 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 10],
                    ['name' => 'name', 'label' => 'Name', 'type' => DynFieldType::TEXT, 'is_required' => true, 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 20],
                    ['name' => 'description', 'label' => 'Description', 'type' => DynFieldType::TEXTAREA, 'column_span' => 12, 'field_order' => 30],
                    [
                        'name' => 'default_priority',
                        'label' => 'Default priority',
                        'type' => DynFieldType::SELECT,
                        'show_in_table' => true,
                        'is_filterable' => true,
                        'options_json' => ['Critical', 'High', 'Medium', 'Low'],
                        'field_order' => 40,
                    ],
                    ['name' => 'sla_hours', 'label' => 'SLA hours', 'type' => DynFieldType::NUMBER, 'show_in_table' => true, 'field_order' => 50],
                ],
            ],
            [
                'slug' => 'site_tickets',
                'name' => 'Site Tickets',
                'description' => 'ATC site / maintenance / incident tickets (dynamic fields). Not native IT helpdesk.',
                'sort_order' => 20,
                'fields' => [
                    ['name' => 'ticket_number', 'label' => 'Ticket #', 'type' => DynFieldType::TEXT, 'is_required' => true, 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 10],
                    ['name' => 'subject', 'label' => 'Subject', 'type' => DynFieldType::TEXT, 'is_required' => true, 'show_in_table' => true, 'is_filterable' => true, 'column_span' => 12, 'field_order' => 20],
                    ['name' => 'description', 'label' => 'Description', 'type' => DynFieldType::TEXTAREA, 'column_span' => 12, 'field_order' => 30],
                    [
                        'name' => 'ticket_type',
                        'label' => 'Type',
                        'type' => DynFieldType::SELECT,
                        'show_in_table' => true,
                        'is_filterable' => true,
                        'options_json' => ['Incident', 'Request', 'Maintenance', 'Preventive'],
                        'field_order' => 40,
                    ],
                    [
                        'name' => 'priority',
                        'label' => 'Priority',
                        'type' => DynFieldType::SELECT,
                        'show_in_table' => true,
                        'is_filterable' => true,
                        'options_json' => ['Critical', 'High', 'Medium', 'Low'],
                        'field_order' => 50,
                    ],
                    [
                        'name' => 'status',
                        'label' => 'Status',
                        'type' => DynFieldType::SELECT,
                        'show_in_table' => true,
                        'is_filterable' => true,
                        'options_json' => ['Open', 'In Progress', 'On Hold', 'Resolved', 'Closed'],
                        'field_order' => 60,
                    ],
                    ['name' => 'category_id', 'label' => 'Category', 'type' => DynFieldType::RELATIONSHIP, 'target_slug' => 'ticket_categories', 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 70],
                    ['name' => 'tower_site_id', 'label' => 'Tower site', 'type' => DynFieldType::RELATIONSHIP, 'target_slug' => 'tower_sites', 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 80],
                    ['name' => 'reported_by', 'label' => 'Reported by', 'type' => DynFieldType::TEXT, 'show_in_table' => true, 'field_order' => 90],
                    ['name' => 'assigned_to', 'label' => 'Assigned to', 'type' => DynFieldType::TEXT, 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 100],
                    ['name' => 'reported_at', 'label' => 'Reported at', 'type' => DynFieldType::DATETIME, 'show_in_table' => true, 'field_order' => 110],
                    ['name' => 'due_at', 'label' => 'Due at', 'type' => DynFieldType::DATETIME, 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 120],
                    ['name' => 'resolved_at', 'label' => 'Resolved at', 'type' => DynFieldType::DATETIME, 'field_order' => 130],
                    ['name' => 'root_cause', 'label' => 'Root cause', 'type' => DynFieldType::TEXTAREA, 'column_span' => 12, 'field_order' => 140],
                    ['name' => 'resolution_notes', 'label' => 'Resolution notes', 'type' => DynFieldType::TEXTAREA, 'column_span' => 12, 'field_order' => 150],
                ],
            ],
            [
                'slug' => 'ticket_activities',
                'name' => 'Ticket Activities',
                'description' => 'Work log / comments against site tickets.',
                'sort_order' => 30,
                'fields' => [
                    ['name' => 'site_ticket_id', 'label' => 'Site ticket', 'type' => DynFieldType::RELATIONSHIP, 'target_slug' => 'site_tickets', 'is_required' => true, 'show_in_table' => true, 'is_filterable' => true, 'field_order' => 10],
                    [
                        'name' => 'activity_type',
                        'label' => 'Activity type',
                        'type' => DynFieldType::SELECT,
                        'show_in_table' => true,
                        'is_filterable' => true,
                        'options_json' => ['Comment', 'Status change', 'Assignment', 'Site visit', 'Other'],
                        'field_order' => 20,
                    ],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => DynFieldType::TEXTAREA, 'is_required' => true, 'column_span' => 12, 'show_in_table' => true, 'field_order' => 30],
                    ['name' => 'activity_at', 'label' => 'Activity at', 'type' => DynFieldType::DATETIME, 'show_in_table' => true, 'field_order' => 40],
                    ['name' => 'performed_by', 'label' => 'Performed by', 'type' => DynFieldType::TEXT, 'show_in_table' => true, 'field_order' => 50],
                ],
            ],
        ];
    }

    /**
     * Default category seed rows (values_json).
     *
     * @return list<array{title: string, status: string, values: array<string, mixed>}>
     */
    public static function defaultCategories(): array
    {
        return [
            [
                'title' => 'Power / Generator',
                'status' => 'Active',
                'values' => [
                    'code' => 'PWR',
                    'name' => 'Power / Generator',
                    'description' => 'Site power, genset, fuel, ATS.',
                    'default_priority' => 'High',
                    'sla_hours' => 24,
                ],
            ],
            [
                'title' => 'Access / Security',
                'status' => 'Active',
                'values' => [
                    'code' => 'ACS',
                    'name' => 'Access / Security',
                    'description' => 'Gate, lock, CCTV, site access.',
                    'default_priority' => 'High',
                    'sla_hours' => 48,
                ],
            ],
            [
                'title' => 'Structural / Civil',
                'status' => 'Active',
                'values' => [
                    'code' => 'CIV',
                    'name' => 'Structural / Civil',
                    'description' => 'Tower structure, compound, civil works.',
                    'default_priority' => 'Medium',
                    'sla_hours' => 72,
                ],
            ],
            [
                'title' => 'Tenant / Colocation',
                'status' => 'Active',
                'values' => [
                    'code' => 'TEN',
                    'name' => 'Tenant / Colocation',
                    'description' => 'Tenant equipment / colocation issues.',
                    'default_priority' => 'Medium',
                    'sla_hours' => 48,
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allThroughPhase5(): array
    {
        return array_values(array_unique([
            ...AtcFinancePack::allThroughPhase3(),
            ...self::phase5EntitySlugs(),
        ]));
    }
}
