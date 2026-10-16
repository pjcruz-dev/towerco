<?php

declare(strict_types=1);

namespace Tests\Unit\AiAssistant;

use App\Modules\AiAssistant\Http\Requests\AskAssistantRequest;
use App\Modules\AiAssistant\Support\AssistantModelPricing;
use Tests\TestCase;

final class AssistantModelPricingTest extends TestCase
{
    public function test_models_sort_highest_php_price_first(): void
    {
        $sorted = AssistantModelPricing::sortHighestFirst([
            'composer-2.5',
            'grok-4.7',
            'claude-opus-5',
            'default',
        ]);

        $this->assertSame('claude-opus-5', $sorted[0]);
        $this->assertSame('grok-4.7', $sorted[1]);
        $this->assertSame('default', $sorted[array_key_last($sorted)]);

        $opus = AssistantModelPricing::preview('claude-opus-5');
        $auto = AssistantModelPricing::preview('default');
        $this->assertGreaterThan($auto['high'], $opus['high']);
        $this->assertSame('PHP', $opus['currency']);
        $this->assertStringStartsWith('Est: ₱', $opus['label']);
    }

    public function test_ask_request_keeps_the_selected_model(): void
    {
        $request = AskAssistantRequest::create('/api/v1/assistant/ask', 'POST', [
            'question' => 'SN-IMPORT-1002 change the status value of it',
            'preferred_model' => 'grok-4.7',
            'plan_mode' => false,
        ]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        $validated = $request->validated();
        $this->assertSame('grok-4.7', $validated['preferred_model']);
        $this->assertFalse($validated['plan_mode']);
    }
}
