<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Modules\AiAssistant\Services\Actions\AssistantActionRouter;
use App\Modules\AiAssistant\Services\Actions\CreateHtmlReportFromTemplateAction;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class HtmlReportTemplateActionTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_assistant.actions.enabled' => true]);
        $this->bootInMemoryTenantApi();
    }

    public function test_creating_the_procurement_dashboard_saves_the_template(): void
    {
        tenancy()->initialize($this->testTenant);
        try {
            $match = app(AssistantActionRouter::class)->match('Create the procurement approval pipeline');
            $this->assertNotNull($match);
            $this->assertSame('create_html_report_from_template', $match['action']);
            $this->assertSame('procurement-approval-pipeline', $match['args']['template_key']);

            $action = app(CreateHtmlReportFromTemplateAction::class);
            $draft = $action->propose($this->testTenantAdmin, 'Create the procurement approval pipeline', $match['args']);
            $this->assertNull(DynHtmlReport::query()->where('slug', 'procurement-approval-pipeline')->first());

            $result = $action->execute($this->testTenantAdmin, $draft->payload);
            $this->assertTrue($result->ok);
            $this->assertSame('/dynamic-entities/html-reports/procurement-approval-pipeline', $result->href);

            $row = DynHtmlReport::query()->where('slug', 'procurement-approval-pipeline')->first();
            $this->assertNotNull($row);
            $this->assertStringContainsString('procurement-dash', (string) $row->html_source);
            $this->assertStringContainsString('AppFramework', (string) $row->js_source);
            $this->assertNull($row->builder_json);
        } finally {
            tenancy()->end();
        }
    }

    public function test_creating_the_ticketing_dashboard_saves_that_template(): void
    {
        tenancy()->initialize($this->testTenant);
        try {
            $match = app(AssistantActionRouter::class)->match('create the ticketing html report dashboard');
            $this->assertNotNull($match);
            $this->assertSame('create_html_report_from_template', $match['action']);
            $this->assertSame('ticketing-dashboard', $match['args']['template_key']);

            $action = app(CreateHtmlReportFromTemplateAction::class);
            $draft = $action->propose($this->testTenantAdmin, 'create the ticketing html report dashboard', $match['args']);
            $result = $action->execute($this->testTenantAdmin, $draft->payload);
            $this->assertTrue($result->ok);

            $row = DynHtmlReport::query()->where('slug', 'ticketing-dashboard')->first();
            $this->assertNotNull($row);
            $this->assertStringContainsString('ticketing_tickets', (string) $row->js_source);
            $this->assertNull($row->builder_json);
        } finally {
            tenancy()->end();
        }
    }

    public function test_a_generic_report_request_stays_on_report_builder(): void
    {
        $match = app(AssistantActionRouter::class)->match('Create a bar chart of tower sites');
        $this->assertNotNull($match);
        $this->assertSame('create_html_report_from_prompt', $match['action']);
    }

    public function test_asking_about_the_dashboard_does_not_install_it(): void
    {
        $match = app(AssistantActionRouter::class)->match('What is the procurement dashboard?');
        $this->assertTrue($match === null || ($match['action'] ?? '') !== 'create_html_report_from_template');
    }
}
