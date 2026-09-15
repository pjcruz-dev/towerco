<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Support\AiPromptIntentDetector;
use App\Modules\AiAssistant\Support\AiPromptModuleCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AiPromptCatalogAndIntentTest extends TestCase
{
    #[Test]
    public function tower_sites_full_details_loads_reporting_and_location(): void
    {
        $detector = new AiPromptIntentDetector;
        $intents = $detector->detect('can you mkae full details of tower sites? not only site code?');

        $this->assertContains('reporting', $intents);
        $this->assertContains('location', $intents);
    }

    #[Test]
    public function accounting_co_loads_system_reporting_workflow_printable(): void
    {
        $detector = new AiPromptIntentDetector;
        $intents = $detector->detect('Set up general ledger posting and chart of accounts');

        foreach (['accounting', 'system', 'reporting', 'workflow', 'printable'] as $key) {
            $this->assertContains($key, $intents);
        }
    }

    #[Test]
    public function paper_symptoms_on_report_phrase_rescue_printable(): void
    {
        $detector = new AiPromptIntentDetector;
        $intents = $detector->detect('Fix the html report invoice — letterhead and control number are cut off');

        $this->assertContains('reporting', $intents);
        $this->assertContains('printable', $intents);
    }

    #[Test]
    public function fan_out_phrase_loads_workflow_and_system(): void
    {
        $detector = new AiPromptIntentDetector;
        $intents = $detector->detect('Create one purchase order per supplier from this batch');

        $this->assertContains('workflow', $intents);
        $this->assertContains('system', $intents);
    }

    #[Test]
    public function catalog_bodies_use_metacore_style_markers_without_metacore_tools(): void
    {
        $reporting = (string) AiPromptModuleCatalog::bodyForKey('reporting');
        $workflows = (string) AiPromptModuleCatalog::bodyForKey('workflows');
        $core = (string) AiPromptModuleCatalog::bodyForKey('core');

        $this->assertStringContainsString('format=detail', $reporting);
        $this->assertStringContainsString('CRITICAL', $reporting);
        $this->assertStringContainsString('TYPE A:', $workflows);
        $this->assertStringContainsString('MANDATORY SELF-CHECK', $workflows);
        $this->assertStringContainsString('STRICTLY BANNED PHRASES', $core);
        $this->assertStringContainsString('HTML SURFACE RULES', $reporting);

        foreach ([$reporting, $workflows, $core] as $body) {
            $this->assertStringNotContainsString('PLAN_SYSTEM', $body);
            $this->assertStringNotContainsString('MANAGE_WORKFLOW', $body);
            $this->assertStringNotContainsString('EXECUTE_SQL', $body);
            $this->assertStringNotContainsString('AppFramework', $body);
            $this->assertStringNotContainsString('dat_', $body);
        }
    }

    #[Test]
    public function catalog_defaults_include_expected_module_keys(): void
    {
        $keys = array_map(static fn (array $d): string => $d['key'], AiPromptModuleCatalog::defaults());

        foreach ([
            'router', 'core', 'system_rules', 'erp_architect', 'data_ops', 'frontend_rules',
            'reporting', 'printable_rules', 'workflows', 'accounting', 'location_rules',
            'system_audit', 'pos_standards', 'ticketing', 'e_approval',
        ] as $key) {
            $this->assertContains($key, $keys);
        }
    }
}
