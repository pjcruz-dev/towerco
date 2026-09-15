<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Services\DynReportBuilderAiService;
use App\Modules\DynamicEntities\Services\DynReportBuilderService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class DynReportBuilderDetailHeuristicTest extends TestCase
{
    /**
     * @return list<array{slug: string, name: string, module_pack: string, fields: list<array{name: string, label: string, type: string}>}>
     */
    private function towerSitesCatalog(): array
    {
        return [
            [
                'slug' => 'tower_sites',
                'name' => 'Tower Sites',
                'module_pack' => 'sites',
                'fields' => [
                    ['name' => 'site_code', 'label' => 'Site Code', 'type' => 'string'],
                    ['name' => 'title', 'label' => 'Title', 'type' => 'string'],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'string'],
                    ['name' => 'municipality', 'label' => 'Municipality', 'type' => 'string'],
                    ['name' => 'region', 'label' => 'Region', 'type' => 'string'],
                    ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'number'],
                    ['name' => 'longitude', 'label' => 'Longitude', 'type' => 'number'],
                ],
            ],
            [
                'slug' => 'tickets',
                'name' => 'Tickets',
                'module_pack' => 'ticketing',
                'fields' => [
                    ['name' => 'status', 'label' => 'Status', 'type' => 'string'],
                ],
            ],
        ];
    }

    #[Test]
    public function heuristic_maps_full_details_not_only_site_code_to_detail_listing(): void
    {
        $service = app(DynReportBuilderAiService::class);
        $method = new ReflectionMethod(DynReportBuilderAiService::class, 'buildViaHeuristics');
        $method->setAccessible(true);

        $def = $method->invoke(
            $service,
            'can you mkae full details of tower sites? not only site code?',
            '',
            $this->towerSitesCatalog(),
            ['tower_sites', 'sites'],
            'sites',
        );

        $this->assertSame('detail', $def['format']);
        $this->assertSame('tower_sites', $def['entity_slug']);
        $this->assertSame('', $def['group_by']);
        $this->assertSame('none', $def['chart']);
        $this->assertSame('Tower Sites — Full details', $def['title']);
        $this->assertGreaterThanOrEqual(1000, (int) $def['row_limit']);
    }

    #[Test]
    public function heuristic_maps_dashboard_for_tower_sites_to_detail_listing(): void
    {
        $service = app(DynReportBuilderAiService::class);
        $method = new ReflectionMethod(DynReportBuilderAiService::class, 'buildViaHeuristics');
        $method->setAccessible(true);

        $def = $method->invoke(
            $service,
            'Create Dashboard for tower sites',
            '',
            $this->towerSitesCatalog(),
            ['tower_sites', 'sites'],
            'sites',
        );

        $this->assertSame('detail', $def['format']);
        $this->assertSame('tower_sites', $def['entity_slug']);
        $this->assertSame('', $def['group_by']);
        $this->assertSame('none', $def['chart']);
        $this->assertSame('Tower Sites — Dashboard', $def['title']);
    }

    #[Test]
    public function heuristic_applies_tanza_only_filter_and_bar_chart_summary(): void
    {
        $service = app(DynReportBuilderAiService::class);
        $method = new ReflectionMethod(DynReportBuilderAiService::class, 'buildViaHeuristics');
        $method->setAccessible(true);

        $def = $method->invoke(
            $service,
            'Create Dashboard for tower sites ALL TANZA_CAVITE Only confirm with bar chart',
            '',
            $this->towerSitesCatalog(),
            ['tower_sites', 'sites'],
            'sites',
        );

        $this->assertSame('summary', $def['format']);
        $this->assertSame('bar', $def['chart']);
        $this->assertNotSame('site_code', $def['group_by']);
        $this->assertNotEmpty($def['filters']);
        $this->assertSame('TANZA_CAVITE', $def['filters'][0]['value']);
        $this->assertSame('municipality', $def['filters'][0]['field']);
    }

    #[Test]
    public function human_title_replaces_raw_question_with_entity_label(): void
    {
        $service = app(DynReportBuilderAiService::class);
        $method = new ReflectionMethod(DynReportBuilderAiService::class, 'humanTitle');
        $method->setAccessible(true);

        $title = $method->invoke(
            $service,
            'can you mkae full details of tower sites? not only site code?',
            'Tower Sites',
            'detail',
        );

        $this->assertSame('Tower Sites — Full details', $title);
    }

    #[Test]
    public function extract_json_object_tolerates_markdown_and_trailing_commas(): void
    {
        $service = app(DynReportBuilderAiService::class);
        $method = new ReflectionMethod(DynReportBuilderAiService::class, 'extractJsonObject');
        $method->setAccessible(true);

        $decoded = $method->invoke(
            $service,
            "Sure:\n```json\n{\"title\": \"Sites\", \"format\": \"detail\", \"entity_slug\": \"tower_sites\",}\n```",
        );

        $this->assertIsArray($decoded);
        $this->assertSame('detail', $decoded['format']);
        $this->assertSame('tower_sites', $decoded['entity_slug']);
    }

    #[Test]
    public function detail_preview_exposes_many_scalar_columns_not_just_site_code(): void
    {
        $service = app(DynReportBuilderService::class);
        $method = new ReflectionMethod(DynReportBuilderService::class, 'buildDetail');
        $method->setAccessible(true);

        $result = $method->invoke(
            $service,
            [
                [
                    'id' => '1',
                    'site_code' => 'ATC-NCR-1087',
                    'title' => 'Makati Hub',
                    'status' => 'active',
                    'region' => 'NCR',
                    'latitude' => 14.55,
                    'longitude' => 121.02,
                ],
                [
                    'id' => '2',
                    'site_code' => 'ATC-NCR-1088',
                    'title' => 'BGC Node',
                    'status' => 'active',
                    'region' => 'NCR',
                    'latitude' => 14.55,
                    'longitude' => 121.05,
                ],
            ],
            '',
            'count',
            '',
            50,
            'metric',
            'desc',
            'exact',
        );

        $keys = array_column($result['columns'], 'key');
        $this->assertContains('site_code', $keys);
        $this->assertContains('title', $keys);
        $this->assertContains('status', $keys);
        $this->assertContains('region', $keys);
        $this->assertNotContains('id', $keys);
        $this->assertGreaterThanOrEqual(4, count($keys));
        $this->assertSame(2, $result['meta']['row_count']);
    }
}
