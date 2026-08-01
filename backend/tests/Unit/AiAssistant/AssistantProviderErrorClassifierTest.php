<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Support\AssistantProviderErrorClassifier;
use App\Modules\AiAssistant\Support\AssistantProviderQuotaExceededException;
use RuntimeException;
use Tests\TestCase;

final class AssistantProviderErrorClassifierTest extends TestCase
{
    public function test_classifies_openai_quota_from_exception(): void
    {
        $error = new AssistantProviderQuotaExceededException(
            provider: 'openai',
            message: 'OpenAI embeddings failed (HTTP 429): insufficient_quota',
        );

        $this->assertSame(
            AssistantProviderErrorClassifier::OPENAI_QUOTA_EXCEEDED,
            AssistantProviderErrorClassifier::classify($error),
        );
    }

    public function test_classifies_openai_quota_from_runtime_message(): void
    {
        $error = new RuntimeException(
            'OpenAI embeddings failed (HTTP 429): {"error":{"code":"insufficient_quota"}}',
        );

        $this->assertTrue(AssistantProviderErrorClassifier::isQuotaExceeded($error));
    }

    public function test_returns_chat_answer_and_notice_for_openai_quota(): void
    {
        $code = AssistantProviderErrorClassifier::OPENAI_QUOTA_EXCEEDED;
        $answer = AssistantProviderErrorClassifier::chatAnswerFor($code);
        $notice = AssistantProviderErrorClassifier::noticeFor($code);

        $this->assertNotNull($answer);
        $this->assertStringContainsString('OpenAI quota exceeded', $answer);
        $this->assertSame('openai', $notice['provider'] ?? null);
    }

    public function test_classifies_cursor_rate_limit(): void
    {
        $error = new AssistantProviderQuotaExceededException(
            provider: 'cursor',
            message: 'Cursor agent create failed (HTTP 429): rate limit',
        );

        $this->assertSame(
            AssistantProviderErrorClassifier::CURSOR_RATE_LIMIT_EXCEEDED,
            AssistantProviderErrorClassifier::classify($error),
        );
    }
}
