<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Services\Actions\AssistantActionRouter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AssistantUpdateUserRolesRouterTest extends TestCase
{
    #[Test]
    public function router_matches_email_role_change_phrase(): void
    {
        config(['ai_assistant.actions.enabled' => true]);
        $router = new AssistantActionRouter;

        $match = $router->match('dearquiza@alliancetowers.com change the role to viewer only');
        $this->assertNotNull($match);
        $this->assertSame('update_user_roles', $match['action']);
    }

    #[Test]
    public function router_prefers_role_change_over_report_for_email_asks(): void
    {
        config(['ai_assistant.actions.enabled' => true]);
        $router = new AssistantActionRouter;

        $match = $router->match(
            'dearquiza@alliancetowers.com change the role to viewer only and create dashboard',
        );
        $this->assertNotNull($match);
        $this->assertSame('update_user_roles', $match['action']);
    }

    #[Test]
    public function router_still_builds_team_access_user_dashboard(): void
    {
        config(['ai_assistant.actions.enabled' => true]);
        $router = new AssistantActionRouter;

        $match = $router->match('Team & Access All users, can you create dashboard for it?', 'team_access');
        $this->assertNotNull($match);
        $this->assertSame('create_html_report_from_prompt', $match['action']);
    }
}
