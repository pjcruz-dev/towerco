<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Models\AiAssistantFeedback;
use App\Modules\AiAssistant\Models\AiConversation;
use App\Modules\AiAssistant\Models\AiMessage;
use App\Modules\AiAssistant\Services\FeedbackGapReportService;
use App\Modules\AiAssistant\Support\AssistantAskStatus;
use App\Modules\AiAssistant\Support\AssistantConversationStatus;
use App\Modules\AiAssistant\Support\AssistantFeedbackRating;
use App\Modules\AiAssistant\Support\AssistantMessageRole;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class FeedbackGapReportServiceTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();
    }

    public function test_gaps_join_thumbs_down_to_prior_user_question(): void
    {
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $conversation = AiConversation::query()->create([
            'user_id' => $this->testTenantAdmin->id,
            'title' => 'Form missing',
            'module_context' => 'e_approval',
            'page_path' => '/e-approval/submissions/new',
            'status' => AssistantConversationStatus::ACTIVE,
            'last_message_at' => now(),
        ]);

        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => AssistantMessageRole::USER,
            'content' => 'What if my Document Control form is not listed?',
            'status' => AssistantAskStatus::COMPLETED,
        ]);

        $assistant = AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => AssistantMessageRole::ASSISTANT,
            'content' => 'Wrong full submit guide…',
            'status' => AssistantAskStatus::COMPLETED,
        ]);

        AiAssistantFeedback::query()->create([
            'conversation_id' => $conversation->id,
            'message_id' => $assistant->id,
            'user_id' => $this->testTenantAdmin->id,
            'rating' => AssistantFeedbackRating::DOWN,
            'comment' => 'Wrong answer',
        ]);

        $gaps = app(FeedbackGapReportService::class)->gaps(null, 10);
        $this->assertNotEmpty($gaps);
        $this->assertSame('e_approval', $gaps[0]['module_context']);
        $this->assertStringContainsString('Document Control form is not listed', (string) $gaps[0]['question']);
        $this->assertSame('Wrong answer', $gaps[0]['comment']);

        $summary = app(FeedbackGapReportService::class)->summarize($gaps);
        $this->assertSame(1, $summary['total']);
        $this->assertArrayHasKey('e_approval', $summary['by_module']);

        tenancy()->end();
    }
}
