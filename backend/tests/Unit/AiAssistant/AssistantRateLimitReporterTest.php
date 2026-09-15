<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Support\AssistantRateLimitReporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AssistantRateLimitReporterTest extends TestCase
{
    #[Test]
    public function reports_full_remaining_when_unused(): void
    {
        config(['ai_assistant.rate_limit_per_minute' => 20]);

        $request = Request::create('/api/v1/assistant/meta', 'GET');
        $request->setUserResolver(static fn () => null);

        $status = AssistantRateLimitReporter::forRequest($request);

        $this->assertSame(20, $status['limit']);
        $this->assertSame(20, $status['remaining']);
        $this->assertSame(0, $status['resets_in_seconds']);
    }

    #[Test]
    public function remaining_decreases_after_hit(): void
    {
        config(['ai_assistant.rate_limit_per_minute' => 20]);

        $user = new class
        {
            public function getAuthIdentifier(): string
            {
                return 'user-1';
            }
        };

        $request = Request::create('/api/v1/assistant/ask', 'POST');
        $request->setUserResolver(static fn () => $user);

        $key = md5('assistant'.'user-1|assistant');
        RateLimiter::clear($key);
        RateLimiter::hit($key, 60);

        $status = AssistantRateLimitReporter::forRequest($request);

        $this->assertSame(20, $status['limit']);
        $this->assertSame(19, $status['remaining']);
        $this->assertGreaterThan(0, $status['resets_in_seconds']);
    }
}
