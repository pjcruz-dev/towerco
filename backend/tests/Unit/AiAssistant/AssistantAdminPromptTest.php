<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Support\AssistantAdminPrompt;
use Tests\TestCase;

final class AssistantAdminPromptTest extends TestCase
{
    public function test_asset_sentence_loads_the_asset_section_and_not_metacore_tools(): void
    {
        $prompt = AssistantAdminPrompt::forPlanner('Can you create a dummy 50 data for assets with different scenario?');

        $this->assertStringContainsString('builder for a tenant administrator', $prompt);
        $this->assertStringContainsString('it_assets', $prompt);
        $this->assertStringContainsString('seed_sample_it_assets', $prompt);
        $this->assertStringContainsString('execute_sql', $prompt);
        $this->assertStringNotContainsString('ticketing_tickets', $prompt);
        $this->assertStringNotContainsString('PLAN_SYSTEM', $prompt);
        $this->assertStringNotContainsString('G_SEARCH', $prompt);
    }

    public function test_document_control_form_sentence_loads_the_delete_operation(): void
    {
        $prompt = AssistantAdminPrompt::forPlanner('Remove the Cash Advance form and its submissions and attachments');

        $this->assertStringContainsString('delete_eapproval_form', $prompt);
        $this->assertStringContainsString('every form', $prompt);
        $this->assertStringContainsString('revert_last_admin_change', $prompt);
    }

    public function test_ticket_sentence_loads_the_ticket_section(): void
    {
        $prompt = AssistantAdminPrompt::forPlanner('Close ticket TKT-00001');

        $this->assertStringContainsString('ticketing_tickets', $prompt);
        $this->assertStringNotContainsString('it_assets', $prompt);
    }

    public function test_inactive_user_sentence_loads_the_user_section(): void
    {
        $prompt = AssistantAdminPrompt::forPlanner('set appangandaman@alliancetowers.com to inactive');

        $this->assertStringContainsString('set_user_active', $prompt);
        $this->assertStringNotContainsString('it_assets', $prompt);
    }

    public function test_every_ask_includes_this_tenant_database(): void
    {
        $prompt = AssistantAdminPrompt::forPlanner('Close ticket TKT-00001');

        $this->assertStringContainsString('You manage this tenant', $prompt);
        $this->assertStringContainsString('THIS TENANT DATABASE', $prompt);
        $this->assertStringContainsString('execute_sql', $prompt);
        $this->assertStringContainsString('confirm_tenant_sql', $prompt);
        $this->assertStringContainsString('not COUNT(*) alone', $prompt);
        $this->assertStringContainsString('Do not wipe a table', $prompt);
        $this->assertStringContainsString('DELETE, revert, and customize are tenant administrator functions', $prompt);
        $this->assertStringNotContainsString('PLAN_SYSTEM', $prompt);
    }

    public function test_answer_prompt_keeps_the_outcome_voice(): void
    {
        $prompt = AssistantAdminPrompt::forAnswer();

        $this->assertStringContainsString('Just do the operation', $prompt);
        $this->assertStringContainsString('list each row', $prompt);
        $this->assertStringContainsString('Ada Lopez', $prompt);
        $this->assertStringContainsString('Apply runs it', $prompt);
        $this->assertStringNotContainsString('confirm the card', $prompt);
    }

    public function test_people_question_loads_the_user_section_and_the_protocol_is_last(): void
    {
        $prompt = AssistantAdminPrompt::forPlanner('How many users have admin rights?');

        $this->assertStringContainsString('Table users', $prompt);
        $this->assertStringContainsString('set_user_active', $prompt);
        $schema = strpos($prompt, 'THIS TENANT DATABASE');
        $protocol = strpos($prompt, 'Return one JSON object');
        $this->assertIsInt($schema);
        $this->assertIsInt($protocol);
        $this->assertGreaterThan($schema, $protocol);
        $this->assertStringNotContainsString('You may build tenant structure', $prompt);
        $this->assertStringNotContainsString('Great question', $prompt);
    }
}
