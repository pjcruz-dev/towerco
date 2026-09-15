<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Services\Actions\AssistantActionRouter;
use App\Modules\AiAssistant\Services\Actions\PinHtmlReportToDashboardAction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AssistantBuildReportActionsTest extends TestCase
{
    #[Test]
    public function router_matches_report_and_dashboard_phrases(): void
    {
        config(['ai_assistant.actions.enabled' => true]);
        $router = new AssistantActionRouter;

        $match = $router->match('Build a dashboard for general ledger with filter');
        $this->assertNotNull($match);
        $this->assertSame('create_html_report_from_prompt', $match['action']);

        $generate = $router->match(
            'Can you generate a New Dashboard for tower sites With advance filtering, Charts, Recents and more',
        );
        $this->assertNotNull($generate);
        $this->assertSame('create_html_report_from_prompt', $generate['action']);

        $pin = $router->match('Pin report to dashboard');
        $this->assertNotNull($pin);
        $this->assertSame('pin_html_report_to_dashboard', $pin['action']);
    }

    #[Test]
    public function router_does_not_steal_ticket_phrases(): void
    {
        config(['ai_assistant.actions.enabled' => true]);
        $router = new AssistantActionRouter;

        $match = $router->match('Create a ticket for generator fuel low', 'ticketing');
        $this->assertNotNull($match);
        $this->assertSame('draft_ticket', $match['action']);
    }

    #[Test]
    public function pin_merge_layout_inserts_dyn_html_report_widget(): void
    {
        $action = app(PinHtmlReportToDashboardAction::class);
        $next = $action->mergeLayout([
            'enabledWidgetIds' => ['kpis', 'awaiting_me'],
            'widgetOrder' => ['kpis', 'awaiting_me'],
            'hiddenWidgetIds' => [],
            'spans' => [],
            'widgetOptions' => [],
        ], 'gl-summary', 'GL Summary');

        $this->assertContains('dyn_html_report~gl-summary', $next['enabledWidgetIds']);
        $this->assertSame('dyn_html_report~gl-summary', $next['widgetOrder'][0]);
        $this->assertSame('GL Summary', $next['widgetOptions']['dyn_html_report~gl-summary']['title']);
        $this->assertSame(
            'gl-summary',
            $next['widgetOptions']['dyn_html_report~gl-summary']['settings']['reportSlug'],
        );
    }

    #[Test]
    public function router_passes_user_entity_hints_for_team_access_phrases(): void
    {
        config(['ai_assistant.actions.enabled' => true]);
        $router = new AssistantActionRouter;

        $match = $router->match('Team & Access All users, can you create dashboard for it?', 'team_access');
        $this->assertNotNull($match);
        $this->assertSame('create_html_report_from_prompt', $match['action']);
        $this->assertSame('team_access', $match['args']['module_context'] ?? null);
        $this->assertContains('users', $match['args']['entity_hints'] ?? []);
        $this->assertContains('users_system', $match['args']['entity_hints'] ?? []);
    }
}
