<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Support\AssistantDailyUsageReporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AssistantDailyUsageReporterTest extends TestCase
{
    #[Test]
    public function daily_limit_disabled_when_zero(): void
    {
        config(['ai_assistant.daily_ask_limit' => 0]);

        $request = Request::create('/api/v1/assistant/meta', 'GET');
        $request->setUserResolver(static fn () => new class
        {
            public function getAuthIdentifier(): string
            {
                return 'user-1';
            }
        });

        $this->assertNull(AssistantDailyUsageReporter::forRequest($request));
        $this->assertFalse(AssistantDailyUsageReporter::wouldExceed($request));
    }

    #[Test]
    public function daily_remaining_decreases_after_record(): void
    {
        config(['ai_assistant.daily_ask_limit' => 5]);
        Cache::flush();

        $request = Request::create('/api/v1/assistant/ask', 'POST');
        $request->setUserResolver(static fn () => new class
        {
            public function getAuthIdentifier(): string
            {
                return 'user-daily';
            }
        });

        $before = AssistantDailyUsageReporter::forRequest($request);
        $this->assertNotNull($before);
        $this->assertSame(5, $before['remaining']);

        AssistantDailyUsageReporter::recordAsk($request);

        $after = AssistantDailyUsageReporter::forRequest($request);
        $this->assertNotNull($after);
        $this->assertSame(4, $after['remaining']);
    }

    #[Test]
    public function would_exceed_when_at_limit(): void
    {
        config(['ai_assistant.daily_ask_limit' => 2]);
        Cache::flush();

        $request = Request::create('/api/v1/assistant/ask', 'POST');
        $request->setUserResolver(static fn () => new class
        {
            public function getAuthIdentifier(): string
            {
                return 'user-cap';
            }
        });

        AssistantDailyUsageReporter::recordAsk($request);
        AssistantDailyUsageReporter::recordAsk($request);

        $this->assertTrue(AssistantDailyUsageReporter::wouldExceed($request));
    }
}
