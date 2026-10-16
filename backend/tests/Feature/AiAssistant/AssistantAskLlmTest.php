<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Modules\AiAssistant\Support\PromptSecurityService;
use Tests\TestCase;

final class AssistantAskLlmTest extends TestCase
{
    public function test_prompt_security_redacts_secrets(): void
    {
        $service = app(PromptSecurityService::class);
        $redacted = $service->sanitizeUserText('password=SuperSecret123 and Bearer abcdefghijklmnop');

        $this->assertStringContainsString('[REDACTED]', $redacted);
        $this->assertStringNotContainsString('SuperSecret123', $redacted);
        $this->assertStringNotContainsString('abcdefghijklmnop', $redacted);
    }
}
